<?php

namespace App\Console\Commands;

use App\Enums\StatusCategory;
use App\Models\Project;
use App\Services\WorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 既存プロジェクトに既定のワークフローを入れ、固定 3 ステータスを statuses へ移す。
 *
 * M5（テーブル追加）のあと、M6（NOT NULL 化）の前に実行する。
 * 何度実行しても結果が変わらない（冪等）。--dry-run で影響範囲だけ確認できる。
 *
 * 旧 tasks.status は残したままなので、この時点までなら
 * workflows:rollback で元に戻せる。
 */
class InstallWorkflows extends Command
{
    protected $signature = 'workflows:install {--dry-run : 変更を加えずに影響範囲だけ表示する}';

    protected $description = '既存プロジェクトにワークフローを導入し、固定ステータスを移行する';

    /**
     * 旧 TaskStatus の値 → 既定ワークフローのステータス名。
     * 旧 doing は In Progress に寄せる（In Review には自動では入れない）。
     */
    private const STATUS_MAP = [
        'todo' => 'To Do',
        'doing' => 'In Progress',
        'done' => 'Done',
    ];

    public function handle(WorkflowService $workflows): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('--dry-run: 変更は保存しません。');
        }

        if (! $this->legacyColumnExists()) {
            $this->info('tasks.status がありません。M7 まで適用済みで、移行は完了しています。');

            return self::SUCCESS;
        }

        $pending = DB::table('tasks')->whereNull('status_id')->count();
        $this->line("移行対象の課題: {$pending} 件 / プロジェクト: ".Project::count().' 件');

        if ($pending === 0) {
            $this->info('移行対象はありません（すでに移行済みです）。');

            return self::SUCCESS;
        }

        foreach (Project::orderBy('id')->get() as $project) {
            if ($dryRun) {
                $count = $project->issues()->whereNull('status_id')->count();
                $this->line("  - {$project->key}: 課題 {$count} 件（dry-run）");

                continue;
            }

            DB::transaction(function () use ($project, $workflows) {
                $workflows->installDefaults($project);

                $moved = $this->mapStatuses($project);

                $this->line("  - {$project->key}: 課題 {$moved} 件 / ステータス "
                    .$project->statuses()->count().' 件');
            });
        }

        return $dryRun ? self::SUCCESS : $this->verify();
    }

    private function legacyColumnExists(): bool
    {
        return DB::getSchemaBuilder()->hasColumn('tasks', 'status');
    }

    /**
     * 旧 status 文字列を status_id に写す。
     */
    private function mapStatuses(Project $project): int
    {
        $byName = $project->statuses()->get()->keyBy('name');
        $moved = 0;

        foreach (self::STATUS_MAP as $legacy => $name) {
            $status = $byName[$name] ?? null;

            if ($status === null) {
                continue;
            }

            $moved += DB::table('tasks')
                ->where('project_id', $project->id)
                ->whereNull('status_id')
                ->where('status', $legacy)
                ->update(['status_id' => $status->id]);
        }

        // 見覚えのない値が入っていた場合も取りこぼさず、初期ステータスへ寄せる
        $orphans = DB::table('tasks')
            ->where('project_id', $project->id)
            ->whereNull('status_id')
            ->update(['status_id' => $project->initialStatus()->id]);

        if ($orphans > 0) {
            $this->warn("    未知のステータスだった {$orphans} 件を初期ステータスへ移しました。");
        }

        return $moved + $orphans;
    }

    /**
     * 欠損ゼロの検証。1 件でも残れば非ゼロ終了して M6 を止める。
     */
    private function verify(): int
    {
        $problems = collect([
            'status_id が埋まっていない課題が残っている' => DB::table('tasks')->whereNull('status_id')->exists(),
            'ワークフローの無いプロジェクトが残っている' => Project::doesntHave('statuses')->exists(),
            '完了ステータスの無いプロジェクトがある' => Project::whereDoesntHave(
                'statuses',
                fn ($query) => $query->where('category', StatusCategory::Done),
            )->exists(),
        ])->filter()->keys();

        if ($problems->isNotEmpty()) {
            $problems->each(fn (string $problem) => $this->error("  NG: {$problem}"));
            $this->error('移行は完了していません。M6 を適用せず、原因を調べてください。');

            return self::FAILURE;
        }

        $this->info('ワークフローの導入が完了しました。データの欠損はありません。');

        return self::SUCCESS;
    }
}
