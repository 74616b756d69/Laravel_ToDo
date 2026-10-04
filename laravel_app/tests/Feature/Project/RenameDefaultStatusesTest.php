<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 既定ステータスを日本語名にするマイグレーションの検証。
 *
 * 新しいプロジェクトは最初から日本語名で作られるので、
 * 英語名に戻した状態を作ってから up / down を直接呼ぶ。
 */
class RenameDefaultStatusesTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_04_110000_rename_default_statuses_to_japanese.php';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::personalFor(User::factory()->create());
    }

    private function migration(): object
    {
        return require base_path(self::MIGRATION);
    }

    /** @return list<string> */
    private function names(): array
    {
        return $this->project->statuses()->orderBy('position')->pluck('name')->all();
    }

    public function test_新しいプロジェクトは日本語名で作られる(): void
    {
        $this->assertSame(['未着手', '進行中', 'レビュー中', '完了'], $this->names());
    }

    public function test_英語の既定名を日本語にし_戻すこともできる(): void
    {
        $this->migration()->down();
        $this->assertSame(['To Do', 'In Progress', 'In Review', 'Done'], $this->names());

        $this->migration()->up();
        $this->assertSame(['未着手', '進行中', 'レビュー中', '完了'], $this->names());
    }

    public function test_管理者が付け直した名前には触れない(): void
    {
        $this->migration()->down();
        $this->project->statuses()->where('name', 'In Review')->update(['name' => 'QA']);

        $this->migration()->up();

        $this->assertSame(['未着手', '進行中', 'QA', '完了'], $this->names());
    }

    public function test_同じ名前がすでにあれば一意制約に当てずに飛ばす(): void
    {
        $this->migration()->down();
        // 「完了」という名前を別のステータスがすでに使っている
        $this->project->statuses()->where('name', 'In Review')->update(['name' => '完了']);

        $this->migration()->up();

        $this->assertSame(['未着手', '進行中', '完了', 'Done'], $this->names());
    }
}
