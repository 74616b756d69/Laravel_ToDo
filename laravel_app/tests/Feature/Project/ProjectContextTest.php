<?php

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ヘッダーからのプロジェクト切り替えと、切り替えた先が
 * ボード・バックログ・課題の作成先に効いているかを見る。
 */
class ProjectContextTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $first;

    private Project $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        // 個人プロジェクト（いちばん古い所属）が既定になる
        $this->first = Project::personalFor($this->user);
        $this->second = Project::factory()->withMember($this->user)->create(['key' => 'SECOND']);
    }

    private function switchTo(Project $project): void
    {
        $this->actingAs($this->user)
            ->from(route('board'))
            ->patch(route('projects.switch'), ['project' => $project->id])
            ->assertRedirect(route('board'));
    }

    public function test_既定はいちばん古い所属プロジェクト(): void
    {
        $this->assertTrue(
            $this->first->is(app(ProjectContext::class)->current($this->user)),
        );
    }

    public function test_切り替えるとボードの中身が入れ替わる(): void
    {
        Issue::factory()->inProject($this->first, $this->user)->create(['title' => '個人の課題']);
        Issue::factory()->inProject($this->second, $this->user)->create(['title' => '2 つめの課題']);

        $this->switchTo($this->second);

        $lanes = $this->actingAs($this->user)->get(route('board'))->assertOk()->viewData('lanes');

        $titles = $lanes->flatMap(fn (array $lane) => $lane['tasks']->pluck('title'))->all();

        $this->assertSame(['2 つめの課題'], $titles);
    }

    public function test_切り替えるとバックログの中身が入れ替わる(): void
    {
        Issue::factory()->inProject($this->first, $this->user)->create(['title' => '個人の課題']);
        Issue::factory()->inProject($this->second, $this->user)->create(['title' => '2 つめの課題']);

        $this->switchTo($this->second);

        $backlog = $this->actingAs($this->user)->get(route('backlog'))->assertOk()->viewData('backlog');

        $this->assertSame(['2 つめの課題'], $backlog->pluck('title')->all());
    }

    public function test_切り替えた先のプロジェクトで採番されて課題が作られる(): void
    {
        $this->switchTo($this->second);

        $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => '切り替え先の課題',
            'status' => $this->second->initialStatus()->id,
            'priority' => 'medium',
        ])->assertRedirect();

        $task = Issue::where('title', '切り替え先の課題')->sole();

        $this->assertSame($this->second->id, $task->project_id);
        $this->assertSame('SECOND-1', $task->key());
    }

    public function test_クイック追加も切り替えた先のプロジェクトに入る(): void
    {
        $this->switchTo($this->second);

        $this->actingAs($this->user)
            ->post(route('tasks.quick'), ['quick' => '切り替え先にクイック追加'])
            ->assertRedirect();

        $task = Issue::where('title', '切り替え先にクイック追加')->sole();

        $this->assertSame($this->second->id, $task->project_id);
    }

    public function test_所属していないプロジェクトへは切り替えられない(): void
    {
        $others = Project::factory()->withMember(User::factory()->create())->create();

        $this->actingAs($this->user)
            ->patch(route('projects.switch'), ['project' => $others->id])
            ->assertForbidden();
    }

    public function test_所属から外れたら既定へ落ちる(): void
    {
        $this->switchTo($this->second);

        $this->second->members()->where('user_id', $this->user->id)->delete();

        $project = $this->actingAs($this->user)->get(route('board'))->assertOk()->viewData('project');

        $this->assertTrue($this->first->is($project));
    }

    public function test_切り替え後は元居た画面に戻る(): void
    {
        $this->actingAs($this->user)
            ->from(route('backlog'))
            ->patch(route('projects.switch'), ['project' => $this->second->id])
            ->assertRedirect(route('backlog'));
    }

    public function test_一覧はプロジェクトを横断したまま絞り込みだけができる(): void
    {
        Issue::factory()->inProject($this->first, $this->user)->create(['title' => '個人の課題']);
        Issue::factory()->inProject($this->second, $this->user)->create(['title' => '2 つめの課題']);

        $all = $this->actingAs($this->user)->get(route('tasks.index'))->assertOk()->viewData('tasks');
        $this->assertCount(2, $all);

        $filtered = $this->actingAs($this->user)
            ->get(route('tasks.index', ['project' => $this->second->id]))
            ->assertOk()
            ->viewData('tasks');

        $this->assertSame(['2 つめの課題'], $filtered->pluck('title')->all());
    }

    public function test_閲覧者でも切り替えられる(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();

        $this->actingAs($viewer)
            ->from(route('board'))
            ->patch(route('projects.switch'), ['project' => $project->id])
            ->assertRedirect(route('board'));

        $this->assertTrue($project->is(app(ProjectContext::class)->current($viewer)));
    }
}
