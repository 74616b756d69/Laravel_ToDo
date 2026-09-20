<?php

namespace App\Console\Commands;

use App\Enums\StatusCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * workflows:install を巻き戻す。
 *
 * status_id を NULL に戻し、ステータスと遷移の定義を消す。
 * 旧 tasks.status は移行中ずっと無傷なので、これだけで元の状態に戻る。
 *
 * M7（status の drop）を適用したあとは使えない。
 */
class RollbackWorkflows extends Command
{
    protected $signature = 'workflows:rollback
                            {--dry-run : 変更を加えずに影響範囲だけ表示する}
                            {--force : 確認を省略する}';

    protected $description = 'workflows:install によるワークフロー導入を巻き戻す';

    public function handle(): int
    {
        if (! DB::getSchemaBuilder()->hasColumn('tasks', 'status')) {
            $this->error('tasks.status がありません。M7 の適用後は巻き戻せません。');
            $this->error('データベースのバックアップから復元してください。');

            return self::FAILURE;
        }

        $this->line('NULL に戻す課題: '.DB::table('tasks')->whereNotNull('status_id')->count().' 件');
        $this->line('削除するステータス: '.DB::table('statuses')->count().' 件');
        $this->line('削除する遷移: '.DB::table('transitions')->count().' 件');

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: 変更は保存しませんでした。');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('巻き戻しますか？')) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            $this->restoreLegacyStatus();

            // 先に課題の参照を外さないと、restrictOnDelete でステータスを消せない
            DB::table('tasks')->update(['status_id' => null]);
            DB::table('transitions')->delete();
            DB::table('statuses')->delete();
        });

        $this->info('巻き戻しました。この状態で migrate:rollback を実行できます。');

        return self::SUCCESS;
    }

    /**
     * status_id のカテゴリから旧 status 文字列を復元する。
     *
     * M7 の down() は status 列を既定値つきで作り直すだけなので、
     * これをやらないと全件が todo になってしまう。
     *
     * ひとつだけ元に戻らないものがある: In Review に居た課題は
     * 旧 3 値に対応する値が無いため doing に寄る。
     * カテゴリが同じなので画面上の扱いは変わらないが、
     * 「レビュー中だった」という区別だけは失われる。
     */
    private function restoreLegacyStatus(): void
    {
        $map = [
            StatusCategory::Todo->value => 'todo',
            StatusCategory::InProgress->value => 'doing',
            StatusCategory::Done->value => 'done',
        ];

        foreach ($map as $category => $legacy) {
            $statusIds = DB::table('statuses')->where('category', $category)->pluck('id');

            if ($statusIds->isEmpty()) {
                continue;
            }

            DB::table('tasks')->whereIn('status_id', $statusIds)->update(['status' => $legacy]);
        }

        $inReview = DB::table('tasks')
            ->join('statuses', 'statuses.id', '=', 'tasks.status_id')
            ->where('statuses.name', 'In Review')
            ->count();

        if ($inReview > 0) {
            $this->warn("In Review に居た {$inReview} 件は doing に寄せました（旧 3 値に対応する値が無いため）。");
        }
    }
}
