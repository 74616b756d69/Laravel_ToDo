<?php

namespace Tests\Feature\Sprint;

use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * バックログ画面と、スプリント間の課題移動。
 */
class BacklogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::personalFor($this->user);
    }

    private function issue(?Sprint $sprint = null, array $attributes = []): Issue
    {
        return Issue::factory()
            ->inProject($this->project, $this->user)
            ->inCategory(StatusCategory::Todo)
            ->create(['sprint_id' => $sprint?->id, ...$attributes]);
    }

    // --- 画面 ---------------------------------------------------------------

    public function test_上段にスプリント_下段にバックログが並ぶ(): void
    {
        $sprint = Sprint::factory()->for($this->project)->create(['name' => 'Sprint 1']);
        $inSprint = $this->issue($sprint, ['title' => 'スプリントの課題']);
        $inBacklog = $this->issue(null, ['title' => 'バックログの課題']);

        $response = $this->actingAs($this->user)->get(route('backlog'))->assertOk();

        $sprints = $response->viewData('sprints');
        $this->assertCount(1, $sprints);
        $this->assertSame([$inSprint->id], $sprints->first()['issues']->pluck('id')->all());
        $this->assertSame([$inBacklog->id], $response->viewData('backlog')->pluck('id')->all());
    }

    public function test_完了したスプリントは上段に出さない(): void
    {
        Sprint::factory()->for($this->project)->create(['name' => '未開始']);
        Sprint::factory()->for($this->project)->closed()->create(['name' => '完了ずみ']);

        $response = $this->actingAs($this->user)->get(route('backlog'))->assertOk();

        $this->assertSame(['未開始'], $response->viewData('sprints')->pluck('sprint.name')->all());
        $this->assertSame(1, $response->viewData('closedCount'));
    }

    public function test_進行中が先頭に並ぶ(): void
    {
        Sprint::factory()->for($this->project)->create(['name' => '未開始']);
        Sprint::factory()->for($this->project)->active()->create(['name' => '進行中']);

        $response = $this->actingAs($this->user)->get(route('backlog'))->assertOk();

        $this->assertSame(['進行中', '未開始'], $response->viewData('sprints')->pluck('sprint.name')->all());
    }

    public function test_サブタスクは単独で並ばない(): void
    {
        $parent = $this->issue(null, ['title' => '親課題']);
        Issue::factory()->childOf($parent)->create(['title' => '子課題']);

        $backlog = $this->actingAs($this->user)->get(route('backlog'))->viewData('backlog');

        $this->assertSame([$parent->id], $backlog->pluck('id')->all());
    }

    public function test_ストーリーポイントが表示される(): void
    {
        $this->issue(null, ['title' => '見積り済み', 'story_points' => 8]);

        $this->actingAs($this->user)
            ->get(route('backlog'))
            ->assertOk()
            ->assertSee('見積り済み')
            ->assertSee('8');
    }

    public function test_未ログインでは開けない(): void
    {
        $this->get(route('backlog'))->assertRedirect(route('login'));
    }

    // --- 課題の移動 -----------------------------------------------------------

    public function test_バックログからスプリントへ移せる(): void
    {
        $sprint = Sprint::factory()->for($this->project)->create();
        $issue = $this->issue();

        $this->actingAs($this->user)
            ->patchJson(route('backlog.move', $issue), ['sprint' => $sprint->id, 'ids' => [$issue->id]])
            ->assertOk()
            ->assertJsonPath('sprint', $sprint->id);

        $this->assertSame($sprint->id, $issue->refresh()->sprint_id);
    }

    public function test_スプリントからバックログへ戻せる(): void
    {
        $sprint = Sprint::factory()->for($this->project)->create();
        $issue = $this->issue($sprint);

        $this->actingAs($this->user)
            ->patchJson(route('backlog.move', $issue), ['sprint' => null, 'ids' => [$issue->id]])
            ->assertOk()
            ->assertJsonPath('sprintName', 'バックログ');

        $this->assertNull($issue->refresh()->sprint_id);
    }

    public function test_スプリント間で移せる(): void
    {
        $from = Sprint::factory()->for($this->project)->create(['name' => 'A']);
        $to = Sprint::factory()->for($this->project)->create(['name' => 'B']);
        $issue = $this->issue($from);

        $this->actingAs($this->user)
            ->patchJson(route('backlog.move', $issue), ['sprint' => $to->id, 'ids' => [$issue->id]])
            ->assertOk();

        $this->assertSame($to->id, $issue->refresh()->sprint_id);
    }

    public function test_送った順序どおりに並び順が保存される(): void
    {
        $sprint = Sprint::factory()->for($this->project)->create();
        $issues = collect(range(1, 3))->map(fn () => $this->issue($sprint));
        $reordered = $issues->reverse()->values();

        $this->actingAs($this->user)->patchJson(route('backlog.move', $reordered->first()), [
            'sprint' => $sprint->id,
            'ids' => $reordered->pluck('id')->all(),
        ])->assertOk();

        $this->assertSame(
            $reordered->pluck('id')->all(),
            Issue::whereIn('id', $issues->pluck('id'))->orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_完了したスプリントへは移せない(): void
    {
        $closed = Sprint::factory()->for($this->project)->closed()->create();
        $issue = $this->issue();

        $this->actingAs($this->user)
            ->patchJson(route('backlog.move', $issue), ['sprint' => $closed->id, 'ids' => [$issue->id]])
            ->assertStatus(422);

        $this->assertNull($issue->refresh()->sprint_id);
    }

    public function test_別プロジェクトのスプリントへは移せない(): void
    {
        $foreign = Sprint::factory()->create();
        $issue = $this->issue();

        $this->actingAs($this->user)
            ->patchJson(route('backlog.move', $issue), ['sprint' => $foreign->id, 'ids' => [$issue->id]])
            ->assertNotFound();

        $this->assertNull($issue->refresh()->sprint_id);
    }

    public function test_参加していないプロジェクトの課題は動かせない(): void
    {
        $others = Issue::factory()->create();

        $this->actingAs($this->user)
            ->patchJson(route('backlog.move', $others), ['sprint' => null, 'ids' => [$others->id]])
            ->assertNotFound();
    }

    public function test_参加していないプロジェクトの課題の並び順は書き換えられない(): void
    {
        $mine = $this->issue(null, ['position' => 0]);
        $others = Issue::factory()->create(['position' => 99]);

        $this->actingAs($this->user)->patchJson(route('backlog.move', $mine), [
            'sprint' => null,
            'ids' => [$others->id, $mine->id],
        ])->assertOk();

        $this->assertSame(99, $others->refresh()->position);
    }

    // --- 権限 ---------------------------------------------------------------

    public function test_閲覧者はスプリントを作れない(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();
        $sprint = Sprint::factory()->for($project)->create();

        $this->actingAs($viewer)
            ->patch(route('sprints.start', $sprint))
            ->assertForbidden();
    }

    public function test_閲覧者には操作ボタンが出ない(): void
    {
        $viewer = User::factory()->create();
        Project::personalFor($viewer);

        // 個人プロジェクトでは自分が admin なので、権限を落として確かめる
        $project = Project::personalFor($viewer);
        $project->members()->where('user_id', $viewer->id)->update(['role' => ProjectRole::Viewer]);

        $this->actingAs($viewer)
            ->get(route('backlog'))
            ->assertOk()
            ->assertDontSee('スプリントを作成');
    }

    public function test_閲覧者は課題を移動できない(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();
        $issue = Issue::factory()->inProject($project)->create();

        $this->actingAs($viewer)
            ->patchJson(route('backlog.move', $issue), ['sprint' => null, 'ids' => [$issue->id]])
            ->assertForbidden();
    }
}
