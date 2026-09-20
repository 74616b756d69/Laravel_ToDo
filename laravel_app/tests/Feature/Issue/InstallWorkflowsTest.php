<?php

namespace Tests\Feature\Issue;

use App\Enums\StatusCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 固定 3 ステータス → ワークフローへの移行コマンドの検証。
 *
 * テスト DB は最新まで適用済みなので、M5〜M7 をロールバックして
 * 「ワークフロー導入前」（tasks.status がある状態）を再現してから確かめる。
 */
class InstallWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ここまで戻せば「ワークフロー導入前」になる（フェーズ 2 完了・フェーズ 3 未適用）。
     *
     * 件数で数えるとマイグレーションを足すたびに壊れるので、名前を基準にする。
     */
    private const LAST_PHASE2_MIGRATION = '2026_09_21_100200_drop_subtasks_table_and_user_id_from_tasks';

    /** workflows:install が動く前提は「M5 だけ適用済み」 */
    private const STATUSES_MIGRATION =
        'database/migrations/2026_09_21_110000_create_statuses_and_transitions_tables.php';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // 先にプロジェクトを作る（この時点ではワークフローつき）
        $this->project = Project::factory()->create(['key' => 'DEMO']);

        // いったんフェーズ 3 以降を戻し、M5 だけ入れ直す。
        // これが「テーブルはあるがワークフロー未導入」＝コマンドの前提状態
        $this->rollbackAfter(self::LAST_PHASE2_MIGRATION);
        Artisan::call('migrate', ['--force' => true, '--path' => self::STATUSES_MIGRATION]);

        $this->assertTrue(Schema::hasColumn('tasks', 'status'), 'M7 の down() が status を戻していません');
        $this->assertTrue(Schema::hasTable('statuses'), 'M5 が適用されていません');
        $this->assertSame(0, DB::table('statuses')->count(), 'ワークフローが残っています');
    }

    /**
     * 指定したマイグレーションより後を、すべてロールバックする。
     */
    private function rollbackAfter(string $migration): void
    {
        $steps = DB::table('migrations')->where('migration', '>', $migration)->count();

        Artisan::call('migrate:rollback', ['--step' => $steps, '--force' => true]);
    }

    /**
     * 旧スキーマの形で課題を 1 件作る。
     */
    private function legacyIssue(string $status, array $attributes = []): int
    {
        $number = (int) DB::table('tasks')->where('project_id', $this->project->id)->max('issue_number') + 1;

        return DB::table('tasks')->insertGetId([
            'project_id' => $this->project->id,
            'issue_number' => $number,
            'issue_type' => 'task',
            'reporter_id' => User::factory()->create()->id,
            'title' => "レガシー課題 {$number}",
            'status' => $status,
            'priority' => 'medium',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_既定のワークフローが導入される(): void
    {
        $this->legacyIssue('todo');

        $this->artisan('workflows:install')->assertSuccessful();

        $this->assertSame(
            ['To Do', 'In Progress', 'In Review', 'Done'],
            $this->project->statuses()->pluck('name')->all(),
        );
        $this->assertSame(8, $this->project->transitions()->count());
    }

    public function test_旧ステータスが対応するステータスへ移る(): void
    {
        $todo = $this->legacyIssue('todo');
        $doing = $this->legacyIssue('doing');
        $done = $this->legacyIssue('done');

        $this->artisan('workflows:install')->assertSuccessful();

        $byName = $this->project->statuses()->get()->keyBy('name');

        $this->assertSame($byName['To Do']->id, (int) DB::table('tasks')->where('id', $todo)->value('status_id'));
        $this->assertSame($byName['In Progress']->id, (int) DB::table('tasks')->where('id', $doing)->value('status_id'));
        $this->assertSame($byName['Done']->id, (int) DB::table('tasks')->where('id', $done)->value('status_id'));
    }

    public function test_件数の内訳が保たれる(): void
    {
        collect(range(1, 5))->each(fn () => $this->legacyIssue('todo'));
        collect(range(1, 3))->each(fn () => $this->legacyIssue('doing'));
        collect(range(1, 2))->each(fn () => $this->legacyIssue('done'));

        $this->artisan('workflows:install')->assertSuccessful();

        $byCategory = DB::table('tasks')
            ->join('statuses', 'statuses.id', '=', 'tasks.status_id')
            ->selectRaw('statuses.category as category, count(*) as aggregate')
            ->groupBy('statuses.category')
            ->pluck('aggregate', 'category');

        $this->assertSame(5, (int) $byCategory[StatusCategory::Todo->value]);
        $this->assertSame(3, (int) $byCategory[StatusCategory::InProgress->value]);
        $this->assertSame(2, (int) $byCategory[StatusCategory::Done->value]);
    }

    public function test_見覚えのないステータスは初期ステータスへ寄せる(): void
    {
        $orphan = $this->legacyIssue('unknown');

        $this->artisan('workflows:install')->assertSuccessful();

        $this->assertSame(
            $this->project->initialStatus()->id,
            (int) DB::table('tasks')->where('id', $orphan)->value('status_id'),
        );
    }

    public function test_子課題も取りこぼさない(): void
    {
        $parent = $this->legacyIssue('doing');
        $child = $this->legacyIssue('done', ['issue_type' => 'subtask', 'parent_id' => $parent]);

        $this->artisan('workflows:install')->assertSuccessful();

        $this->assertNotNull(DB::table('tasks')->where('id', $child)->value('status_id'));
        $this->assertSame(0, DB::table('tasks')->whereNull('status_id')->count());
    }

    public function test_二重に実行しても結果が変わらない(): void
    {
        $this->legacyIssue('todo');
        $this->legacyIssue('done');

        $this->artisan('workflows:install')->assertSuccessful();

        $snapshot = DB::table('tasks')->orderBy('id')->get()->toJson();

        $this->artisan('workflows:install')->assertSuccessful();

        $this->assertSame($snapshot, DB::table('tasks')->orderBy('id')->get()->toJson());
        $this->assertSame(4, $this->project->statuses()->count());
        $this->assertSame(8, $this->project->transitions()->count());
    }

    public function test_dry_runは何も変更しない(): void
    {
        $this->legacyIssue('todo');

        $this->artisan('workflows:install', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('tasks')->whereNull('status_id')->count());
        $this->assertSame(0, DB::table('statuses')->count());
    }

    public function test_導入後に残りのマイグレーションを適用できる(): void
    {
        $parent = $this->legacyIssue('doing');
        $this->legacyIssue('done', ['issue_type' => 'subtask', 'parent_id' => $parent]);

        $this->artisan('workflows:install')->assertSuccessful();

        Artisan::call('migrate', ['--force' => true]);

        $this->assertFalse(Schema::hasColumn('tasks', 'status'));
        // 親子とも残っている
        $this->assertSame(2, DB::table('tasks')->count());
        $this->assertSame(1, DB::table('tasks')->whereNotNull('parent_id')->count());
    }

    // --- ロールバック -------------------------------------------------------

    public function test_巻き戻すと旧ステータスが復元される(): void
    {
        $todo = $this->legacyIssue('todo');
        $doing = $this->legacyIssue('doing');
        $done = $this->legacyIssue('done');

        $this->artisan('workflows:install')->assertSuccessful();
        $this->artisan('workflows:rollback', ['--force' => true])->assertSuccessful();

        // M7 の down() は status を既定値で作り直すだけなので、
        // コマンド側が status_id のカテゴリから値を書き戻している
        $this->assertSame('todo', DB::table('tasks')->where('id', $todo)->value('status'));
        $this->assertSame('doing', DB::table('tasks')->where('id', $doing)->value('status'));
        $this->assertSame('done', DB::table('tasks')->where('id', $done)->value('status'));

        $this->assertSame(0, DB::table('statuses')->count());
        $this->assertSame(0, DB::table('transitions')->count());
        $this->assertSame(3, DB::table('tasks')->whereNull('status_id')->count());
    }

    public function test_In_Reviewはdoingに寄る(): void
    {
        $id = $this->legacyIssue('doing');

        $this->artisan('workflows:install')->assertSuccessful();

        // In Review へ移してから巻き戻す
        $review = $this->project->statuses()->where('name', 'In Review')->sole();
        DB::table('tasks')->where('id', $id)->update(['status_id' => $review->id]);

        $this->artisan('workflows:rollback', ['--force' => true])->assertSuccessful();

        // 旧 3 値に In Review は無いので doing に寄る（カテゴリは同じ）
        $this->assertSame('doing', DB::table('tasks')->where('id', $id)->value('status'));
    }

    public function test_巻き戻したあともう一度導入できる(): void
    {
        $this->legacyIssue('doing');

        $this->artisan('workflows:install')->assertSuccessful();
        $this->artisan('workflows:rollback', ['--force' => true])->assertSuccessful();
        $this->artisan('workflows:install')->assertSuccessful();

        $this->assertSame(0, DB::table('tasks')->whereNull('status_id')->count());
        $this->assertSame(4, $this->project->statuses()->count());
    }

    public function test_M7適用後は巻き戻せないと知らせる(): void
    {
        $this->legacyIssue('todo');
        $this->artisan('workflows:install')->assertSuccessful();
        Artisan::call('migrate', ['--force' => true]);

        $this->assertFalse(Schema::hasColumn('tasks', 'status'));
        $this->artisan('workflows:rollback', ['--force' => true])->assertFailed();
    }
}
