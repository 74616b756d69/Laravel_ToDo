<?php

namespace App\Console\Commands;

use App\Enums\IssueType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * issues:migrate-from-tasks を巻き戻す。
 *
 * 移送した子課題を消し、親タスクの新カラムを NULL に戻す。
 * subtasks テーブルは移行時に一切触っていないので、これだけで元の状態に戻る。
 *
 * M4（subtasks の drop）を適用したあとは使えない。その場合はバックアップから戻すしかない。
 */
class RollbackIssueMigration extends Command
{
    protected $signature = 'issues:rollback-migration
                            {--dry-run : 変更を加えずに影響範囲だけ表示する}
                            {--force : 確認を省略する}';

    protected $description = 'issues:migrate-from-tasks による移行を巻き戻す';

    public function handle(): int
    {
        if (! $this->schemaIsRollbackable()) {
            $this->error('subtasks テーブルがありません。M4 の適用後は巻き戻せません。');
            $this->error('データベースのバックアップから復元してください。');

            return self::FAILURE;
        }

        $children = DB::table('tasks')->where('issue_type', IssueType::Subtask->value)->count();
        $migrated = DB::table('tasks')->whereNotNull('project_id')->count();

        $this->line("削除する子課題: {$children} 件");
        $this->line('NULL に戻す課題: '.($migrated - $children).' 件');
        $this->line('subtasks テーブル: '.DB::table('subtasks')->count().' 件（無傷）');

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: 変更は保存しませんでした。');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('巻き戻しますか？')) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            // 移送した子課題を物理削除する。元データは subtasks に残っている
            DB::table('tasks')->where('issue_type', IssueType::Subtask->value)->delete();

            DB::table('tasks')->update([
                'project_id' => null,
                'issue_number' => null,
                'issue_type' => IssueType::Task->value,
                'parent_id' => null,
                'reporter_id' => null,
                'assignee_id' => null,
            ]);

            DB::table('projects')->update(['last_issue_number' => 0]);
        });

        $this->info('巻き戻しました。この状態で migrate:rollback を実行できます。');

        return self::SUCCESS;
    }

    private function schemaIsRollbackable(): bool
    {
        return DB::getSchemaBuilder()->hasTable('subtasks')
            && DB::getSchemaBuilder()->hasColumn('tasks', 'user_id');
    }
}
