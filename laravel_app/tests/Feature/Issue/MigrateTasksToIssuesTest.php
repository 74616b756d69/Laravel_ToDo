<?php

namespace Tests\Feature\Issue;

use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 移行コマンドの検証。
 *
 * テスト DB は最新まで適用済みなので、まずフェーズ 2・3 のマイグレーションを
 * すべてロールバックして「移行前のスキーマ」（subtasks テーブルと
 * tasks.user_id / tasks.status がある状態）に戻し、旧形式のデータを
 * 流し込んでから移行コマンドを走らせる。
 * 各 down() が本当に戻せることも、この手順で同時に確かめている。
 */
class MigrateTasksToIssuesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ここまで戻せば「フェーズ 2 の移行コマンドを流せる状態」になる（M1 適用済み）。
     *
     * 件数で数えるとマイグレーションを足すたびに壊れるので、名前を基準にする。
     */
    private const LAST_LEGACY_MIGRATION = '2026_09_21_100000_add_issue_columns_to_tasks_table';

    /**
     * フェーズ 3 の移行コマンドを実行できる状態（M5 適用済み）までのマイグレーション。
     *
     * migrate の --step は「1 本ずつ別バッチにする」フラグで件数指定ではないので、
     * 途中まで進めたいときはパスで指定する。
     */
    private const UP_TO_STATUSES = [
        'database/migrations/2026_09_21_100100_tighten_issue_columns_on_tasks_table.php',
        'database/migrations/2026_09_21_100200_drop_subtasks_table_and_user_id_from_tasks.php',
        'database/migrations/2026_09_21_110000_create_statuses_and_transitions_tables.php',
    ];

    /**
     * 指定したマイグレーションより後を、すべてロールバックする。
     *
     * マイグレーション名は日付順に並ぶので、文字列比較でそのまま「後ろ」を数えられる。
     */
    private function rollbackAfter(string $migration): void
    {
        $steps = DB::table('migrations')->where('migration', '>', $migration)->count();

        Artisan::call('migrate:rollback', ['--step' => $steps, '--force' => true]);
    }

    /**
     * M3・M4・M5 を順に適用する。
     */
    private function migrateUpToStatuses(): void
    {
        foreach (self::UP_TO_STATUSES as $path) {
            Artisan::call('migrate', ['--force' => true, '--path' => $path]);
        }
    }

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        // M1 より後をすべて戻し、移行前のスキーマを再現する
        $this->rollbackAfter(self::LAST_LEGACY_MIGRATION);

        $this->assertTrue(Schema::hasTable('subtasks'), 'M4 の down() が subtasks を戻していません');
        $this->assertTrue(Schema::hasColumn('tasks', 'user_id'), 'M4 の down() が user_id を戻していません');

        $this->alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
    }

    /**
     * 移行前の形でタスクを 1 件作る。
     */
    private function legacyTask(User $user, array $attributes = []): int
    {
        return DB::table('tasks')->insertGetId([
            'user_id' => $user->id,
            'title' => 'レガシータスク',
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    private function legacySubtask(int $taskId, array $attributes = []): int
    {
        return DB::table('subtasks')->insertGetId([
            'task_id' => $taskId,
            'title' => 'レガシーサブタスク',
            'is_done' => false,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_すべてのタスクが個人プロジェクトへ移る(): void
    {
        $this->legacyTask($this->alice, ['title' => 'Aliceの1件目']);
        $this->legacyTask($this->alice, ['title' => 'Aliceの2件目']);
        $this->legacyTask($this->bob, ['title' => 'Bobの1件目']);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $this->assertSame(0, DB::table('tasks')->whereNull('project_id')->count());

        $aliceProject = Project::personalFor($this->alice);
        $bobProject = Project::personalFor($this->bob);

        $this->assertNotSame($aliceProject->id, $bobProject->id);
        $this->assertSame(2, $aliceProject->issues()->count());
        $this->assertSame(1, $bobProject->issues()->count());
    }

    public function test_移行したユーザーは自分のプロジェクトの管理者になる(): void
    {
        $this->legacyTask($this->alice);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $project = Project::personalFor($this->alice);

        $this->assertSame(
            ProjectRole::Admin,
            $project->roleFor($this->alice),
        );
    }

    public function test_課題番号は作成順に1から振られる(): void
    {
        $old = $this->legacyTask($this->alice, ['title' => '古い', 'created_at' => now()->subDays(3)]);
        $new = $this->legacyTask($this->alice, ['title' => '新しい', 'created_at' => now()]);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $this->assertSame(1, (int) DB::table('tasks')->where('id', $old)->value('issue_number'));
        $this->assertSame(2, (int) DB::table('tasks')->where('id', $new)->value('issue_number'));
    }

    public function test_user_idがreporterとassigneeに引き継がれる(): void
    {
        $id = $this->legacyTask($this->alice);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $row = DB::table('tasks')->where('id', $id)->first();

        $this->assertSame($this->alice->id, (int) $row->reporter_id);
        $this->assertSame($this->alice->id, (int) $row->assignee_id);
        $this->assertSame(IssueType::Task->value, $row->issue_type);
    }

    public function test_サブタスクがSubtask型の課題として移送される(): void
    {
        $taskId = $this->legacyTask($this->alice, ['title' => '親タスク']);
        $this->legacySubtask($taskId, ['title' => '未完了の子', 'is_done' => false]);
        $this->legacySubtask($taskId, ['title' => '完了した子', 'is_done' => true, 'position' => 1]);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $children = DB::table('tasks')
            ->where('parent_id', $taskId)
            ->orderBy('position')
            ->get();

        $this->assertCount(2, $children);
        $this->assertSame(IssueType::Subtask->value, $children[0]->issue_type);

        // is_done が旧 status 文字列に写っていること（このフェーズではまだ文字列）
        $this->assertSame('todo', $children[0]->status);
        $this->assertSame('done', $children[1]->status);
        $this->assertNotNull($children[1]->completed_at);

        // 子にも課題番号が振られていること
        $this->assertNotNull($children[0]->issue_number);
        $this->assertNotNull($children[1]->issue_number);
    }

    public function test_元のsubtasksテーブルは残る(): void
    {
        $taskId = $this->legacyTask($this->alice);
        $this->legacySubtask($taskId);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        // 巻き戻せるようにするため、移行では一切消さない
        $this->assertSame(1, DB::table('subtasks')->count());
    }

    public function test_ソフトデリート済みのタスクも取りこぼさない(): void
    {
        $id = $this->legacyTask($this->alice, ['deleted_at' => now()]);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $row = DB::table('tasks')->where('id', $id)->first();

        $this->assertNotNull($row->project_id);
        $this->assertNotNull($row->issue_number);
    }

    public function test_タグの紐付けは失われない(): void
    {
        $taskId = $this->legacyTask($this->alice);
        $tagId = DB::table('tags')->insertGetId([
            'user_id' => $this->alice->id, 'name' => '仕事', 'color' => 'sky',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tag_task')->insert(['task_id' => $taskId, 'tag_id' => $tagId]);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $this->assertSame(1, DB::table('tag_task')->count());
        $this->assertSame(1, Issue::find($taskId)->tags()->count());
    }

    public function test_二重に実行しても結果が変わらない(): void
    {
        $taskId = $this->legacyTask($this->alice);
        $this->legacySubtask($taskId, ['title' => '子1']);
        $this->legacySubtask($taskId, ['title' => '子2', 'position' => 1]);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $snapshot = DB::table('tasks')->orderBy('id')->get()->toJson();
        $projects = DB::table('projects')->count();

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $this->assertSame($snapshot, DB::table('tasks')->orderBy('id')->get()->toJson());
        $this->assertSame($projects, DB::table('projects')->count());
    }

    public function test_dry_runは何も変更しない(): void
    {
        $this->legacyTask($this->alice);

        $this->artisan('issues:migrate-from-tasks', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('tasks')->whereNull('project_id')->count());
        $this->assertSame(0, DB::table('projects')->count());
    }

    public function test_移行後に残りのマイグレーションを適用できる(): void
    {
        $taskId = $this->legacyTask($this->alice);
        $this->legacySubtask($taskId);

        // フェーズ 2 の移行 → M3・M4・M5 → フェーズ 3 の移行 → M6・M7
        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();
        $this->migrateUpToStatuses();

        $this->artisan('workflows:install')->assertSuccessful();
        Artisan::call('migrate', ['--force' => true]);

        $this->assertFalse(Schema::hasTable('subtasks'));
        $this->assertFalse(Schema::hasColumn('tasks', 'user_id'));
        $this->assertFalse(Schema::hasColumn('tasks', 'status'));
        // データは残っている（親 1 + 子 1）。ステータスも埋まっている
        $this->assertSame(2, DB::table('tasks')->count());
        $this->assertSame(0, DB::table('tasks')->whereNull('status_id')->count());
    }

    public function test_未移行のまま_M3を適用すると止まる(): void
    {
        $this->legacyTask($this->alice);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('issues:migrate-from-tasks');

        Artisan::call('migrate', ['--force' => true]);
    }

    public function test_ワークフロー未導入のまま_M6を適用すると止まる(): void
    {
        $this->legacyTask($this->alice);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();
        // M3・M4・M5 までは通る
        $this->migrateUpToStatuses();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('workflows:install');

        Artisan::call('migrate', ['--force' => true]);
    }

    // --- ロールバック -------------------------------------------------------

    public function test_巻き戻すと元の状態に戻る(): void
    {
        $taskId = $this->legacyTask($this->alice, ['title' => '親タスク']);
        $this->legacySubtask($taskId, ['title' => '子タスク']);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();
        $this->assertSame(2, DB::table('tasks')->count());

        $this->artisan('issues:rollback-migration', ['--force' => true])->assertSuccessful();

        // 移送した子課題は消え、親は移行前の形に戻る
        $this->assertSame(1, DB::table('tasks')->count());

        $row = DB::table('tasks')->where('id', $taskId)->first();
        $this->assertNull($row->project_id);
        $this->assertNull($row->issue_number);
        $this->assertNull($row->reporter_id);
        $this->assertSame($this->alice->id, (int) $row->user_id);

        // 元データは subtasks に残ったまま
        $this->assertSame(1, DB::table('subtasks')->count());
    }

    public function test_巻き戻したあともう一度移行できる(): void
    {
        $taskId = $this->legacyTask($this->alice);
        $this->legacySubtask($taskId);

        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();
        $this->artisan('issues:rollback-migration', ['--force' => true])->assertSuccessful();
        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();

        $this->assertSame(2, DB::table('tasks')->count());
        $this->assertSame(0, DB::table('tasks')->whereNull('project_id')->count());
    }

    public function test_M4適用後は巻き戻せないと知らせる(): void
    {
        $this->legacyTask($this->alice);
        $this->artisan('issues:migrate-from-tasks')->assertSuccessful();
        $this->migrateUpToStatuses();
        $this->artisan('workflows:install')->assertSuccessful();
        Artisan::call('migrate', ['--force' => true]);

        $this->artisan('issues:rollback-migration', ['--force' => true])->assertFailed();
    }
}
