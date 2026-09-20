<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesWorkflow;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase, UsesWorkflow;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_一覧には自分のタスクだけが表示される(): void
    {
        Issue::factory()->forUser($this->user)->create(['title' => '自分のタスク']);
        Issue::factory()->create(['title' => '他人のタスク']);

        $this->actingAs($this->user)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('自分のタスク')
            ->assertDontSee('他人のタスク');
    }

    public function test_タスクを作成できる(): void
    {
        $response = $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => '新しいタスク',
            'content' => 'メモ',
            'status' => $this->statusIdFor($this->user, 'In Progress'),
            'priority' => TaskPriority::High->value,
            'due_date' => '2026-12-31',
        ]);

        $task = Issue::sole();

        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame($this->user->id, $task->reporter_id);
        $this->assertSame($this->user->id, $task->assignee_id);
        // 個人プロジェクトに自動で所属する
        $this->assertSame(\App\Models\Project::personalFor($this->user)->id, $task->project_id);
        $this->assertSame($this->statusIdFor($this->user, 'In Progress'), $task->status_id);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertNull($task->completed_at);
    }

    public function test_完了状態で作成すると完了日時が入る(): void
    {
        $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => '完了済みタスク',
            'status' => $this->statusIdFor($this->user, 'Done'),
            'priority' => TaskPriority::Low->value,
        ]);

        $this->assertNotNull(Issue::sole()->completed_at);
    }

    public function test_タイトルが空だと作成できない(): void
    {
        $this->actingAs($this->user)
            ->post(route('tasks.store'), [
                'title' => '',
                'status' => $this->statusIdFor($this->user, 'To Do'),
                'priority' => TaskPriority::Low->value,
            ])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_不正なステータスは受け付けない(): void
    {
        $this->actingAs($this->user)
            ->post(route('tasks.store'), [
                'title' => 'タスク',
                'status' => 'unknown',
                'priority' => TaskPriority::Low->value,
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_タスクを更新できる(): void
    {
        $task = Issue::factory()->forUser($this->user)->create(['title' => '変更前']);

        $this->actingAs($this->user)->put(route('tasks.update', $task), [
            'title' => '変更後',
            'content' => null,
            'status' => $this->statusIdFor($this->user, 'To Do'),
            'priority' => TaskPriority::Medium->value,
            'due_date' => null,
        ])->assertRedirect(route('tasks.show', $task));

        $this->assertSame('変更後', $task->refresh()->title);
        $this->assertNull($task->completed_at);
    }

    public function test_タスクを削除するとソフトデリートされる(): void
    {
        $task = Issue::factory()->forUser($this->user)->create();

        $this->actingAs($this->user)
            ->delete(route('tasks.destroy', $task))
            ->assertRedirect(route('tasks.index'));

        $this->assertSoftDeleted($task);
    }

    public function test_完了トグルで状態と完了日時が切り替わる(): void
    {
        $task = Issue::factory()->forUser($this->user)->create([
            'status_id' => $this->statusIdFor($this->user, 'To Do'),
            'completed_at' => null,
        ]);

        $this->actingAs($this->user)->patch(route('tasks.completion', $task));
        $task->refresh();
        $this->assertTrue($task->isCompleted());
        $this->assertNotNull($task->completed_at);

        $this->actingAs($this->user)->patch(route('tasks.completion', $task));
        $task->refresh();
        $this->assertFalse($task->isCompleted());
        $this->assertNull($task->completed_at);
    }
}
