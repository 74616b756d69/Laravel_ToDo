<?php

namespace Tests\Feature\Issue;

use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesWorkflow;
use Tests\TestCase;

/**
 * サブタスク＝親を持つ Subtask 型の課題。
 *
 * 移行前は subtasks テーブルの別モデルだった。URL とルート名（subtasks.*）は
 * 据え置きなので、リクエストの形は移行前と変わらない。
 */
class SubIssueTest extends TestCase
{
    use RefreshDatabase, UsesWorkflow;

    private User $user;

    private Issue $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->task = Issue::factory()->forUser($this->user)->create();
    }

    public function test_サブタスクを追加できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('subtasks.store', $this->task), ['title' => '資料を集める'])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'parent_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'issue_type' => IssueType::Subtask->value,
            'title' => '資料を集める',
            'status_id' => $this->statusIdFor($this->user, 'To Do'),
        ]);
    }

    public function test_サブタスクにも課題キーが振られる(): void
    {
        $this->actingAs($this->user)
            ->post(route('subtasks.store', $this->task), ['title' => '資料を集める']);

        $child = $this->task->children()->sole();

        $this->assertNotNull($child->issue_number);
        $this->assertNotSame($this->task->issue_number, $child->issue_number);
        $this->assertSame("{$this->task->project->key}-{$child->issue_number}", $child->key());
    }

    public function test_追加したサブタスクは末尾に並ぶ(): void
    {
        foreach (['1つ目', '2つ目', '3つ目'] as $title) {
            $this->actingAs($this->user)->post(route('subtasks.store', $this->task), ['title' => $title]);
        }

        $this->assertSame(
            ['1つ目', '2つ目', '3つ目'],
            $this->task->children()->pluck('title')->all(),
        );
    }

    public function test_タイトルが空だと追加できない(): void
    {
        $this->actingAs($this->user)
            ->post(route('subtasks.store', $this->task), ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_サブタスクに子課題は作れない(): void
    {
        $child = Issue::factory()->childOf($this->task)->create();

        // フォーム送信なので、白い 422 ではなく理由つきで元の画面に戻す
        $this->actingAs($this->user)
            ->from(route('tasks.show', $child))
            ->post(route('subtasks.store', $child), ['title' => '孫課題'])
            ->assertRedirect()
            ->assertSessionHasErrors('title');

        $this->assertSame(0, $child->children()->count());
    }

    public function test_完了状態を切り替えられる(): void
    {
        $child = Issue::factory()->childOf($this->task)->create(['status_id' => $this->statusIdFor($this->user, 'To Do')]);

        $this->actingAs($this->user)->patch(route('subtasks.toggle', [$this->task, $child]));
        $this->assertTrue($child->fresh()->isCompleted());
        $this->assertNotNull($child->fresh()->completed_at);

        $this->actingAs($this->user)->patch(route('subtasks.toggle', [$this->task, $child]));
        $this->assertFalse($child->fresh()->isCompleted());
        $this->assertNull($child->fresh()->completed_at);
    }

    public function test_サブタスクを削除できる(): void
    {
        $child = Issue::factory()->childOf($this->task)->create();

        $this->actingAs($this->user)->delete(route('subtasks.destroy', [$this->task, $child]));

        $this->assertDatabaseMissing('tasks', ['id' => $child->id]);
    }

    /**
     * 仕様変更: 子課題は独立した行になったので、親をソフトデリートしたときも
     * 一緒に消えないと一覧に孤児として現れてしまう。
     */
    public function test_親をソフトデリートすると子も消える(): void
    {
        $children = Issue::factory()->count(3)->childOf($this->task)->create();

        $this->task->delete();

        $children->each(fn (Issue $child) => $this->assertSoftDeleted($child));
    }

    public function test_親を復元すると子も戻る(): void
    {
        $child = Issue::factory()->childOf($this->task)->create();

        $this->task->delete();
        $this->task->restore();

        $this->assertNotSoftDeleted($child);
    }

    public function test_親を完全に削除すると子も消える(): void
    {
        Issue::factory()->count(3)->childOf($this->task)->create();

        $this->task->forceDelete();

        $this->assertSame(0, Issue::withTrashed()->where('parent_id', $this->task->id)->count());
    }

    public function test_参加していないプロジェクトの課題にサブタスクを追加できない(): void
    {
        $others = Issue::factory()->create();

        $this->actingAs($this->user)
            ->post(route('subtasks.store', $others), ['title' => '割り込み'])
            ->assertForbidden();
    }

    public function test_閲覧者はサブタスクを追加できない(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();
        $task = Issue::factory()->inProject($project)->create();

        $this->actingAs($viewer)
            ->post(route('subtasks.store', $task), ['title' => '閲覧者による追加'])
            ->assertForbidden();
    }

    public function test_別の課題のサブタスクは操作できない(): void
    {
        $otherTask = Issue::factory()->forUser($this->user)->create();
        $child = Issue::factory()->childOf($otherTask)->create();

        // URL の課題とサブタスクの組み合わせが食い違うケース
        $this->actingAs($this->user)
            ->patch(route('subtasks.toggle', [$this->task, $child]))
            ->assertNotFound();
    }

    public function test_進捗率が計算される(): void
    {
        Issue::factory()->count(3)->childOf($this->task)->create(['status_id' => $this->statusIdFor($this->user, 'To Do')]);
        Issue::factory()->childOf($this->task)->completed()->create();

        $this->assertSame(25, $this->task->load('children.status')->progress());
    }

    public function test_サブタスクが無ければ進捗率は_nullになる(): void
    {
        $this->assertNull($this->task->load('children.status')->progress());
    }

    public function test_サブタスクは一覧に単独で並ばない(): void
    {
        Issue::factory()->childOf($this->task)->create(['title' => '子課題のタイトル']);

        $tasks = $this->actingAs($this->user)->get(route('tasks.index'))->viewData('tasks');

        $this->assertSame(1, $tasks->total());
        $this->assertSame($this->task->id, $tasks->first()->id);
    }
}
