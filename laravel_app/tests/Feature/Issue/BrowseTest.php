<?php

namespace Tests\Feature\Issue;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 課題キーでの移動（/browse/PROJ-123）と、ヘッダーの検索窓の振り分け。
 */
class BrowseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->withMember($this->user)->create(['key' => 'ABC']);
    }

    private function issue(): Issue
    {
        return Issue::factory()->inProject($this->project, $this->user)->create();
    }

    public function test_課題キーで詳細へ飛べる(): void
    {
        $task = $this->issue();

        $this->actingAs($this->user)
            ->get(route('browse', $task->key()))
            ->assertRedirect(route('tasks.show', $task));
    }

    public function test_課題キーは大文字小文字を問わない(): void
    {
        $task = $this->issue();

        $this->actingAs($this->user)
            ->get(route('browse', 'abc-'.$task->issue_number))
            ->assertRedirect(route('tasks.show', $task));
    }

    public function test_存在しないキーは404(): void
    {
        $this->actingAs($this->user)->get(route('browse', 'ABC-999'))->assertNotFound();
        $this->actingAs($this->user)->get(route('browse', 'ZZZ-1'))->assertNotFound();
    }

    public function test_見えない課題のキーも404(): void
    {
        $others = Project::factory()->withMember(User::factory()->create())->create(['key' => 'XYZ']);
        $task = Issue::factory()->inProject($others)->create();

        $this->actingAs($this->user)
            ->get(route('browse', $task->key()))
            ->assertNotFound();
    }

    public function test_ログインしていなければログインへ送られる(): void
    {
        $task = $this->issue();

        $this->get(route('browse', $task->key()))->assertRedirect(route('login'));
    }

    public function test_検索窓に課題キーを入れると詳細へ直行する(): void
    {
        $task = $this->issue();

        $this->actingAs($this->user)
            ->get(route('search', ['q' => $task->key()]))
            ->assertRedirect(route('browse', $task->key()));
    }

    public function test_検索窓のキーワードは一覧の検索へ流れる(): void
    {
        $this->actingAs($this->user)
            ->get(route('search', ['q' => '請求書']))
            ->assertRedirect(route('tasks.index', ['keyword' => '請求書']));
    }

    public function test_空の検索は一覧へ戻す(): void
    {
        $this->actingAs($this->user)
            ->get(route('search', ['q' => '  ']))
            ->assertRedirect(route('tasks.index'));
    }

    public function test_一覧の課題キーが詳細へのリンクになっている(): void
    {
        $task = $this->issue();

        $this->actingAs($this->user)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('href="'.route('tasks.show', $task).'"', escape: false)
            ->assertSee($task->key());
    }
}
