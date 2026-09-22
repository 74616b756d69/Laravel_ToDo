<?php

namespace Tests\Feature\Task;

use App\Enums\ActivityField;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Tag;
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

    // --- その場で編集できる項目 ----------------------------------------------

    public function test_タイトルをその場で書き換えられる(): void
    {
        $task = $this->issue(['title' => '古い要約']);

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.title', $task), ['title' => '新しい要約'])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('新しい要約', $task->refresh()->title);
    }

    public function test_空のタイトルでは保存されない(): void
    {
        $task = $this->issue(['title' => '古い要約']);

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.title', $task), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertSame('古い要約', $task->refresh()->title);
    }

    public function test_説明をその場で書き換えられる(): void
    {
        $task = $this->issue(['content' => '<p>前の説明</p>']);

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.content', $task), ['content' => '<p>あとの説明</p>'])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertStringContainsString('あとの説明', (string) $task->refresh()->content);
    }

    public function test_説明を空にできる(): void
    {
        $task = $this->issue(['content' => '<p>消される説明</p>']);

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.content', $task), ['content' => ''])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertNull($task->refresh()->content);
    }

    public function test_優先度をその場で変えると履歴にも残る(): void
    {
        $task = $this->issue(['priority' => TaskPriority::Low]);

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.priority', $task), ['priority' => TaskPriority::High->value])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame(TaskPriority::High, $task->refresh()->priority);
        $this->assertTrue($task->activities()->where('field', ActivityField::Priority)->exists());
    }

    public function test_期限をその場で設定して外せる(): void
    {
        $task = $this->issue(['due_date' => null]);

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.due-date', $task), ['due_date' => '2026-12-24'])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('2026-12-24', $task->refresh()->due_date->format('Y-m-d'));

        // 空で送れば未設定に戻る
        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.due-date', $task), ['due_date' => ''])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertNull($task->refresh()->due_date);
    }

    public function test_見積りをその場で置き直せる(): void
    {
        $task = $this->issue(['story_points' => null]);

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.story-points', $task), ['story_points' => 5])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame(5, $task->refresh()->story_points);

        // 負の値はカラム（unsignedSmallInteger）に入らないので、検証で止める
        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.story-points', $task), ['story_points' => -1])
            ->assertSessionHasErrors('story_points');

        $this->assertSame(5, $task->refresh()->story_points);
    }

    public function test_タグをその場で付け替えられる(): void
    {
        $task = $this->issue();
        $tag = Tag::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.tags', $task), ['tags' => [$tag->id]])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame([$tag->id], $task->refresh()->tags->pluck('id')->all());

        // 欄ごと空で送れば全部外れる
        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.tags', $task), [])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertTrue($task->refresh()->tags->isEmpty());
    }

    public function test_他人のタグは付けられない(): void
    {
        $task = $this->issue();
        $othersTag = Tag::factory()->for(User::factory()->create())->create();

        $this->actingAs($this->user)
            ->from(route('tasks.show', $task))
            ->patch(route('tasks.tags', $task), ['tags' => [$othersTag->id]])
            ->assertSessionHasErrors('tags.0');

        $this->assertTrue($task->refresh()->tags->isEmpty());
    }

    public function test_閲覧しかできない相手はその場の編集も弾かれる(): void
    {
        $viewer = User::factory()->create();
        $this->project->members()->create(['user_id' => $viewer->id, 'role' => ProjectRole::Viewer]);

        $task = $this->issue(['title' => '触らせない']);

        $this->actingAs($viewer)
            ->patch(route('tasks.title', $task), ['title' => '書き換え'])
            ->assertForbidden();

        $this->assertSame('触らせない', $task->refresh()->title);
    }
}
