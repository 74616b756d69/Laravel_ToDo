<?php

namespace Tests\Feature\Task;

use App\Enums\ActivityField;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesWorkflow;
use Tests\TestCase;

/**
 * 詳細画面から、編集フォームを開かずにできる操作。
 */
class InlineActionTest extends TestCase
{
    use RefreshDatabase, UsesWorkflow;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::personalFor($this->user);
    }

    private function issue(array $attributes = []): Issue
    {
        return Issue::factory()->inProject($this->project, $this->user)->create($attributes);
    }

    // --- ステータス遷移 ------------------------------------------------------

    public function test_詳細画面に次に行ける遷移だけが並ぶ(): void
    {
        $task = $this->issue(['status_id' => $this->statusIdFor($this->user, 'To Do')]);

        $transitions = $this->actingAs($this->user)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->viewData('transitions');

        // 既定ワークフローでは To Do → In Review だけが禁止されている
        $this->assertSame(['In Progress', 'Done'], $transitions->pluck('name')->all());
    }

    public function test_遷移ボタンでステータスが変わる(): void
    {
        $task = $this->issue(['status_id' => $this->statusIdFor($this->user, 'To Do')]);
        $target = $this->statusFor($this->user, 'In Progress');

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.transition', $task), ['status' => $target->id])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame($target->id, $task->refresh()->status_id);
    }

    public function test_完了ステータスへ遷移すると完了日時が入る(): void
    {
        $task = $this->issue(['status_id' => $this->statusIdFor($this->user, 'In Progress')]);

        $this->actingAs($this->user)
            ->patch(route('tasks.transition', $task), ['status' => $this->statusIdFor($this->user, 'Done')]);

        $this->assertNotNull($task->refresh()->completed_at);
    }

    public function test_禁止された遷移は理由を返して状態を変えない(): void
    {
        $task = $this->issue(['status_id' => $this->statusIdFor($this->user, 'To Do')]);
        $before = $task->status_id;

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.transition', $task), ['status' => $this->statusIdFor($this->user, 'In Review')])
            ->assertRedirect(route('tasks.show', $task))
            ->assertSessionHasErrors('status');

        $this->assertSame($before, $task->refresh()->status_id);
    }

    public function test_禁止された遷移はJSONでは422を返す(): void
    {
        $task = $this->issue(['status_id' => $this->statusIdFor($this->user, 'To Do')]);

        $this->actingAs($this->user)
            ->patchJson(route('tasks.transition', $task), ['status' => $this->statusIdFor($this->user, 'In Review')])
            ->assertStatus(422);
    }

    public function test_他プロジェクトのステータスへは遷移できない(): void
    {
        $task = $this->issue();
        $others = Project::factory()->withMember($this->user)->create();

        $this->actingAs($this->user)
            ->patch(route('tasks.transition', $task), ['status' => $others->initialStatus()->id])
            ->assertNotFound();
    }

    public function test_遷移は履歴に残る(): void
    {
        $task = $this->issue(['status_id' => $this->statusIdFor($this->user, 'To Do')]);

        $this->actingAs($this->user)
            ->patch(route('tasks.transition', $task), ['status' => $this->statusIdFor($this->user, 'In Progress')]);

        $this->assertDatabaseHas('activities', [
            'issue_id' => $task->id,
            'field' => ActivityField::Status->value,
            'old_value' => 'To Do',
            'new_value' => 'In Progress',
        ]);
    }

    public function test_閲覧者は遷移できない(): void
    {
        $viewer = User::factory()->create();
        $this->project->members()->create(['user_id' => $viewer->id, 'role' => ProjectRole::Viewer]);

        $task = $this->issue(['status_id' => $this->statusIdFor($this->user, 'To Do')]);

        $this->actingAs($viewer)
            ->patch(route('tasks.transition', $task), ['status' => $this->statusIdFor($this->user, 'In Progress')])
            ->assertForbidden();
    }

    // --- 担当者 --------------------------------------------------------------

    public function test_担当者を付け替えられる(): void
    {
        $other = User::factory()->create();
        $this->project->members()->create(['user_id' => $other->id, 'role' => ProjectRole::Member]);

        $task = $this->issue();

        $this->actingAs($this->user)
            ->patch(route('tasks.assignee', $task), ['assignee' => $other->id])
            ->assertRedirect();

        $this->assertSame($other->id, $task->refresh()->assignee_id);
    }

    public function test_担当者を未割り当てにできる(): void
    {
        $task = $this->issue();

        $this->actingAs($this->user)
            ->patch(route('tasks.assignee', $task), ['assignee' => ''])
            ->assertRedirect();

        $this->assertNull($task->refresh()->assignee_id);
    }

    public function test_自分に割り当てられる(): void
    {
        $other = User::factory()->create();
        $this->project->members()->create(['user_id' => $other->id, 'role' => ProjectRole::Member]);

        $task = $this->issue(['assignee_id' => $other->id]);

        $this->actingAs($this->user)
            ->patch(route('tasks.assignee', $task), ['assignee' => $this->user->id])
            ->assertRedirect();

        $this->assertSame($this->user->id, $task->refresh()->assignee_id);
    }

    public function test_プロジェクトに居ない人は担当者にできない(): void
    {
        $outsider = User::factory()->create();
        $task = $this->issue();

        $this->actingAs($this->user)
            ->patch(route('tasks.assignee', $task), ['assignee' => $outsider->id])
            ->assertSessionHasErrors('assignee');

        $this->assertSame($this->user->id, $task->refresh()->assignee_id);
    }

    public function test_担当者の変更は履歴に残る(): void
    {
        $other = User::factory()->create(['name' => '田中']);
        $this->project->members()->create(['user_id' => $other->id, 'role' => ProjectRole::Member]);

        $task = $this->issue();

        $this->actingAs($this->user)
            ->patch(route('tasks.assignee', $task), ['assignee' => $other->id]);

        $this->assertDatabaseHas('activities', [
            'issue_id' => $task->id,
            'field' => ActivityField::Assignee->value,
            'new_value' => '田中',
        ]);
    }

    // --- 課題タイプ ----------------------------------------------------------

    public function test_課題タイプを変えられる(): void
    {
        $task = $this->issue(['issue_type' => IssueType::Task]);

        $this->actingAs($this->user)
            ->patch(route('tasks.type', $task), ['issue_type' => IssueType::Bug->value])
            ->assertRedirect();

        $this->assertSame(IssueType::Bug, $task->refresh()->issue_type);
    }

    public function test_課題タイプを変えても親子関係は動かない(): void
    {
        $parent = $this->issue();
        $child = Issue::factory()->inProject($this->project, $this->user)->childOf($parent)->create();

        $this->actingAs($this->user)
            ->patch(route('tasks.type', $child), ['issue_type' => IssueType::Bug->value])
            ->assertRedirect();

        $child->refresh();

        $this->assertSame($parent->id, $child->parent_id);
        $this->assertTrue($child->isChild());
    }

    public function test_知らない課題タイプは受け付けない(): void
    {
        $task = $this->issue(['issue_type' => IssueType::Task]);

        $this->actingAs($this->user)
            ->patch(route('tasks.type', $task), ['issue_type' => 'epic-ish'])
            ->assertSessionHasErrors('issue_type');

        $this->assertSame(IssueType::Task, $task->refresh()->issue_type);
    }

    // --- フォーム ------------------------------------------------------------

    public function test_作成フォームから担当者と課題タイプを指定できる(): void
    {
        $other = User::factory()->create();
        $this->project->members()->create(['user_id' => $other->id, 'role' => ProjectRole::Member]);

        $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => 'バグを直す',
            'status' => $this->project->initialStatus()->id,
            'priority' => 'high',
            'issue_type' => IssueType::Bug->value,
            'assignee' => $other->id,
        ])->assertRedirect();

        $task = Issue::where('title', 'バグを直す')->sole();

        $this->assertSame(IssueType::Bug, $task->issue_type);
        $this->assertSame($other->id, $task->assignee_id);
    }

    public function test_担当者を送らなければ従来どおり自分に割り当てられる(): void
    {
        $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => '欄なしで作成',
            'status' => $this->project->initialStatus()->id,
            'priority' => 'medium',
        ])->assertRedirect();

        $task = Issue::where('title', '欄なしで作成')->sole();

        $this->assertSame($this->user->id, $task->assignee_id);
        $this->assertSame(IssueType::Task, $task->issue_type);
    }

    public function test_編集フォームから担当者と課題タイプを変えられる(): void
    {
        $task = $this->issue(['issue_type' => IssueType::Task]);

        $this->actingAs($this->user)->put(route('tasks.update', $task), [
            'title' => $task->title,
            'status' => $task->status_id,
            'priority' => $task->priority->value,
            'issue_type' => IssueType::Story->value,
            'assignee' => '',
        ])->assertRedirect();

        $task->refresh();

        $this->assertSame(IssueType::Story, $task->issue_type);
        $this->assertNull($task->assignee_id);
    }

    public function test_詳細画面に担当者と起票者が出る(): void
    {
        $task = $this->issue();

        $this->actingAs($this->user)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('担当者')
            ->assertSee('起票者')
            ->assertSee('スプリント')
            ->assertSee('ストーリーポイント')
            ->assertSee($this->user->name);
    }
}
