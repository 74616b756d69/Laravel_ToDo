<?php

namespace App\Console\Commands;

use App\Enums\IssueType;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 既存のタスクを課題（Issue）へ移行する。
 *
 * M1（カラム追加）のあと、M3（NOT NULL 化）の前に実行する。
 * 何度実行しても結果が変わらない（冪等）。--dry-run で影響範囲だけ確認できる。
 *
 * 破壊的な操作は一切しない。subtasks テーブルの行も tasks.user_id も残したままなので、
 * この時点までなら issues:rollback-migration で元に戻せる。
 */
class MigrateTasksToIssues extends Command
{
    protected $signature = 'issues:migrate-from-tasks {--dry-run : 変更を加えずに影響範囲だけ表示する}';

    protected $description = '既存のタスクを個人プロジェクト配下の課題へ移行する';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('--dry-run: 変更は保存しません。');
        }

        if (! $this->legacySchemaExists()) {
            $this->info('tasks.user_id がありません。M4 まで適用済みで、移行は完了しています。');

            return self::SUCCESS;
        }

        $before = $this->census();
        $this->line('移行前: '.$this->format($before));

        if ($before['orphan_tasks'] === 0 && $before['pending_subtasks'] === 0) {
            $this->info('移行対象はありません（すでに移行済みです）。');

            return self::SUCCESS;
        }

        foreach ($this->usersWithTasks() as $user) {
            $this->migrateUser($user);
        }

        $after = $this->census();
        $this->line('移行後: '.$this->format($after));

        return $this->verify($before, $after);
    }

    /**
     * 移行前のスキーマ（tasks.user_id）がまだ残っているか。
     * M4 適用後にうっかり実行しても、壊すのではなく何もせず終わるようにする。
     */
    private function legacySchemaExists(): bool
    {
        return DB::getSchemaBuilder()->hasColumn('tasks', 'user_id');
    }

    private function subtasksTableExists(): bool
    {
        return DB::getSchemaBuilder()->hasTable('subtasks');
    }

    /**
     * タスクを持っているユーザー。user_id はこの時点ではまだ生きている。
     *
     * @return Collection<int, User>
     */
    private function usersWithTasks(): Collection
    {
        $ids = DB::table('tasks')->distinct()->pluck('user_id')->filter();

        return User::whereIn('id', $ids)->orderBy('id')->get();
    }

    private function migrateUser(User $user): void
    {
        $pending = DB::table('tasks')
            ->where('user_id', $user->id)
            ->whereNull('project_id')
            ->count();

        if ($pending === 0 && ! $this->hasPendingSubtasks($user)) {
            return;
        }

        if ($this->dryRun) {
            $this->line("  - {$user->name}: タスク {$pending} 件を移行（dry-run）");

            return;
        }

        // ユーザー単位でトランザクションを張る。途中で落ちても他のユーザーには波及しない
        DB::transaction(function () use ($user) {
            $project = Project::personalFor($user);

            $parents = $this->backfillParents($user, $project);
            $children = $this->moveSubtasks($project);

            $this->syncCounter($project);

            $this->line("  - {$user->name} → {$project->key}: 課題 {$parents} 件 / サブタスク {$children} 件");
        });
    }

    private function hasPendingSubtasks(User $user): bool
    {
        if (! $this->subtasksTableExists()) {
            return false;
        }

        return DB::table('subtasks')
            ->join('tasks', 'tasks.id', '=', 'subtasks.task_id')
            ->where('tasks.user_id', $user->id)
            ->exists();
    }

    /**
     * 親タスクに project_id / issue_number / reporter_id などを埋める。
     *
     * 採番は created_at, id の昇順。古いタスクほど小さい番号になり、
     * 「1 番が最初に作ったもの」という自然な期待に沿う。
     */
    private function backfillParents(User $user, Project $project): int
    {
        // ソフトデリート済みも移行対象にする。取りこぼすと復元したとき壊れる
        $rows = DB::table('tasks')
            ->where('user_id', $user->id)
            ->whereNull('project_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id');

        $next = (int) DB::table('projects')->where('id', $project->id)->value('last_issue_number');

        foreach ($rows as $id) {
            DB::table('tasks')->where('id', $id)->update([
                'project_id' => $project->id,
                'issue_number' => ++$next,
                'issue_type' => IssueType::Task->value,
                'reporter_id' => $user->id,
                'assignee_id' => $user->id,
            ]);
        }

        DB::table('projects')->where('id', $project->id)->update(['last_issue_number' => $next]);

        return $rows->count();
    }

    /**
     * subtasks の行を Subtask 型の課題として tasks に作り直す。
     *
     * 元の行は消さない（ロールバックできるようにするため）。
     * M4 でテーブルごと落とすまで両方が残る。
     */
    private function moveSubtasks(Project $project): int
    {
        if (! $this->subtasksTableExists()) {
            return 0;
        }

        $subtasks = DB::table('subtasks')
            ->join('tasks', 'tasks.id', '=', 'subtasks.task_id')
            ->where('tasks.project_id', $project->id)
            ->orderBy('subtasks.task_id')
            ->orderBy('subtasks.position')
            ->orderBy('subtasks.id')
            ->select([
                'subtasks.id',
                'subtasks.task_id',
                'subtasks.title',
                'subtasks.is_done',
                'subtasks.position',
                'subtasks.created_at',
                'subtasks.updated_at',
                'tasks.reporter_id',
                'tasks.assignee_id',
                'tasks.deleted_at as parent_deleted_at',
            ])
            ->get();

        // すでに移送済みの組み合わせは飛ばす（二重実行への備え）
        $existing = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('issue_type', IssueType::Subtask->value)
            ->get(['parent_id', 'title'])
            ->map(fn (object $row) => $row->parent_id.'|'.$row->title)
            ->flip();

        $next = (int) DB::table('projects')->where('id', $project->id)->value('last_issue_number');
        $moved = 0;

        foreach ($subtasks as $subtask) {
            if ($existing->has($subtask->task_id.'|'.$subtask->title)) {
                continue;
            }

            $isDone = (bool) $subtask->is_done;

            DB::table('tasks')->insert([
                'project_id' => $project->id,
                'issue_number' => ++$next,
                'issue_type' => IssueType::Subtask->value,
                'parent_id' => $subtask->task_id,
                'user_id' => $subtask->reporter_id,
                'reporter_id' => $subtask->reporter_id,
                'assignee_id' => $subtask->assignee_id,
                'title' => $subtask->title,
                'content' => null,
                'content_text' => null,
                // is_done しか無かったので、旧 status 文字列の 2 値に写す。
                // ここで status_id を入れないのは、この段階ではまだ
                // ワークフロー（statuses）が存在しないため。workflows:install が後で埋める
                'status' => $isDone ? 'done' : 'todo',
                'priority' => TaskPriority::Medium->value,
                'position' => $subtask->position,
                'due_date' => null,
                // 完了時刻は記録が無いので、最後に触った時刻で代用する
                'completed_at' => $isDone ? $subtask->updated_at : null,
                'created_at' => $subtask->created_at,
                'updated_at' => $subtask->updated_at,
                // 親が消えているなら子も消えている状態に揃える
                'deleted_at' => $subtask->parent_deleted_at,
            ]);

            $moved++;
        }

        DB::table('projects')->where('id', $project->id)->update(['last_issue_number' => $next]);

        return $moved;
    }

    /**
     * 採番カウンタを実データの最大値に合わせる。
     * 途中で手動投入があっても、次の採番が既存とぶつからないようにする。
     */
    private function syncCounter(Project $project): void
    {
        $max = (int) DB::table('tasks')->where('project_id', $project->id)->max('issue_number');

        DB::table('projects')->where('id', $project->id)->update(['last_issue_number' => $max]);
    }

    /**
     * 移行の前後で件数が合っているかを確かめる。
     *
     * @return array<string, int>
     */
    private function census(): array
    {
        $subtasks = $this->subtasksTableExists() ? DB::table('subtasks')->count() : 0;

        return [
            'tasks' => DB::table('tasks')->count(),
            'subtasks' => $subtasks,
            'tag_task' => DB::table('tag_task')->count(),
            'orphan_tasks' => DB::table('tasks')->whereNull('project_id')->count(),
            'pending_subtasks' => $subtasks
                - DB::table('tasks')->where('issue_type', IssueType::Subtask->value)->count(),
        ];
    }

    /** @param  array<string, int>  $census */
    private function format(array $census): string
    {
        return collect($census)->map(fn (int $v, string $k) => "{$k}={$v}")->implode(' / ');
    }

    /**
     * データ欠損ゼロの検証。1 つでも満たさなければ非ゼロ終了して M3 を止める。
     *
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     */
    private function verify(array $before, array $after): int
    {
        if ($this->dryRun) {
            return self::SUCCESS;
        }

        $problems = collect([
            '元のタスクが失われている' => $after['tasks'] < $before['tasks'],
            'subtasks の行が失われている' => $after['subtasks'] !== $before['subtasks'],
            'タグの紐付けが失われている' => $after['tag_task'] !== $before['tag_task'],
            'project_id が埋まっていない課題が残っている' => $after['orphan_tasks'] > 0,
            '移送されていないサブタスクが残っている' => $after['pending_subtasks'] > 0,
            '移送された件数が合わない' => $before['pending_subtasks'] !== $after['tasks'] - $before['tasks'],
            'issue_number が埋まっていない課題が残っている' => Issue::withTrashed()
                ->whereNull('issue_number')->exists(),
        ])->filter()->keys();

        if ($problems->isNotEmpty()) {
            $problems->each(fn (string $problem) => $this->error("  NG: {$problem}"));
            $this->error('移行は完了していません。M3 を適用せず、原因を調べてください。');

            return self::FAILURE;
        }

        $this->info('移行が完了しました。データの欠損はありません。');

        return self::SUCCESS;
    }
}
