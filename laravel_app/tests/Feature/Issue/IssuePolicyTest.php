<?php

namespace Tests\Feature\Issue;

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 役割ごとに課題へ何ができるかを固定する。
 *
 * 移行前は user_id 一致だけだった。判定の根拠を project_members.role に
 * 移したので、admin / member / viewer / 非メンバーの 4 者で確かめる。
 */
class IssuePolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    private User $viewer;

    private User $outsider;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->member = User::factory()->create();
        $this->viewer = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->project = Project::factory()
            ->withMember($this->admin, ProjectRole::Admin)
            ->withMember($this->member, ProjectRole::Member)
            ->withMember($this->viewer, ProjectRole::Viewer)
            ->create();

        // 起票者は admin
        $this->issue = Issue::factory()->inProject($this->project, $this->admin)->create();
    }

    // --- view -------------------------------------------------------------

    public function test_所属していれば閲覧者でも見られる(): void
    {
        foreach ([$this->admin, $this->member, $this->viewer] as $user) {
            $this->assertTrue($user->can('view', $this->issue));
        }
    }

    public function test_非メンバーは見られない(): void
    {
        $this->assertFalse($this->outsider->can('view', $this->issue));
    }

    // --- update -----------------------------------------------------------

    public function test_管理者と一般メンバーは更新できる(): void
    {
        $this->assertTrue($this->admin->can('update', $this->issue));
        $this->assertTrue($this->member->can('update', $this->issue));
    }

    public function test_閲覧者は更新できない(): void
    {
        $this->assertFalse($this->viewer->can('update', $this->issue));
    }

    public function test_閲覧者は画面上でも更新を拒否される(): void
    {
        $this->actingAs($this->viewer)
            ->patch(route('tasks.title', $this->issue), ['title' => '閲覧者による変更'])
            ->assertForbidden();

        $this->assertNotSame('閲覧者による変更', $this->issue->refresh()->title);
    }

    public function test_閲覧者は完了トグルもできない(): void
    {
        $this->actingAs($this->viewer)
            ->patch(route('tasks.completion', $this->issue))
            ->assertForbidden();
    }

    // --- delete -----------------------------------------------------------

    public function test_管理者は他人の課題も削除できる(): void
    {
        $othersIssue = Issue::factory()->inProject($this->project, $this->member)->create();

        $this->assertTrue($this->admin->can('delete', $othersIssue));
    }

    public function test_一般メンバーは自分が起票した課題だけ削除できる(): void
    {
        $own = Issue::factory()->inProject($this->project, $this->member)->create();

        $this->assertTrue($this->member->can('delete', $own));
        // admin が起票したものは消せない
        $this->assertFalse($this->member->can('delete', $this->issue));
    }

    public function test_閲覧者は削除できない(): void
    {
        $this->assertFalse($this->viewer->can('delete', $this->issue));
    }

    // --- create -----------------------------------------------------------

    public function test_閲覧者は課題を作れない(): void
    {
        $this->assertTrue($this->admin->can('create', [Issue::class, $this->project]));
        $this->assertTrue($this->member->can('create', [Issue::class, $this->project]));
        $this->assertFalse($this->viewer->can('create', [Issue::class, $this->project]));
        $this->assertFalse($this->outsider->can('create', [Issue::class, $this->project]));
    }

    // --- 可視範囲（Policy では守れない部分） -------------------------------

    public function test_一覧には参加プロジェクトの課題がすべて出る(): void
    {
        $colleaguesIssue = Issue::factory()
            ->inProject($this->project, $this->member)
            ->create(['title' => '同僚が立てた課題']);

        // viewer から見ても、同じプロジェクトの課題は一覧に並ぶ
        $this->actingAs($this->viewer)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('同僚が立てた課題');

        $this->assertTrue($this->viewer->can('view', $colleaguesIssue));
    }

    public function test_参加していないプロジェクトの課題は一覧に出ない(): void
    {
        Issue::factory()->create(['title' => '別プロジェクトの課題']);

        $this->actingAs($this->admin)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('別プロジェクトの課題');
    }

    /**
     * Policy とスコープが同じ根拠（role）を見ていることの確認。
     * 片方だけ通るとセキュリティホールか、使えない画面になる。
     */
    public function test_一覧に出る課題はすべて閲覧が許可されている(): void
    {
        Issue::factory()->count(3)->inProject($this->project, $this->member)->create();
        Issue::factory()->count(2)->create();

        $visible = Issue::query()->visibleTo($this->viewer)->with('project')->get();

        $this->assertCount(4, $visible);
        $visible->each(fn (Issue $issue) => $this->assertTrue($this->viewer->can('view', $issue)));
    }
}
