<?php

namespace Tests\Feature\Task;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubtaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->task = Task::factory()->for($this->user)->create();
    }

    public function test_サブタスクを追加できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('subtasks.store', $this->task), ['title' => '資料を集める'])
            ->assertRedirect();

        $this->assertDatabaseHas('subtasks', [
            'task_id' => $this->task->id,
            'title' => '資料を集める',
            'is_done' => false,
        ]);
    }

    public function test_追加したサブタスクは末尾に並ぶ(): void
    {
        foreach (['1つ目', '2つ目', '3つ目'] as $title) {
            $this->actingAs($this->user)->post(route('subtasks.store', $this->task), ['title' => $title]);
        }

        $this->assertSame(
            ['1つ目', '2つ目', '3つ目'],
            $this->task->subtasks()->pluck('title')->all(),
        );
    }

    public function test_タイトルが空だと追加できない(): void
    {
        $this->actingAs($this->user)
            ->post(route('subtasks.store', $this->task), ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_完了状態を切り替えられる(): void
    {
        $subtask = Subtask::factory()->for($this->task)->create(['is_done' => false]);

        $this->actingAs($this->user)->patch(route('subtasks.toggle', [$this->task, $subtask]));
        $this->assertTrue($subtask->fresh()->is_done);

        $this->actingAs($this->user)->patch(route('subtasks.toggle', [$this->task, $subtask]));
        $this->assertFalse($subtask->fresh()->is_done);
    }

    public function test_サブタスクを削除できる(): void
    {
        $subtask = Subtask::factory()->for($this->task)->create();

        $this->actingAs($this->user)->delete(route('subtasks.destroy', [$this->task, $subtask]));

        $this->assertDatabaseMissing('subtasks', ['id' => $subtask->id]);
    }

    public function test_タスクを削除するとサブタスクも消える(): void
    {
        Subtask::factory()->count(3)->for($this->task)->create();

        $this->task->forceDelete();

        $this->assertDatabaseCount('subtasks', 0);
    }

    public function test_他人のタスクにサブタスクを追加できない(): void
    {
        $othersTask = Task::factory()->for(User::factory())->create();

        $this->actingAs($this->user)
            ->post(route('subtasks.store', $othersTask), ['title' => '割り込み'])
            ->assertForbidden();
    }

    public function test_別タスクのサブタスクは操作できない(): void
    {
        $otherTask = Task::factory()->for($this->user)->create();
        $subtask = Subtask::factory()->for($otherTask)->create();

        // URL のタスクとサブタスクの組み合わせが食い違うケース
        $this->actingAs($this->user)
            ->patch(route('subtasks.toggle', [$this->task, $subtask]))
            ->assertNotFound();
    }

    public function test_進捗率が計算される(): void
    {
        Subtask::factory()->count(3)->for($this->task)->create(['is_done' => false]);
        Subtask::factory()->for($this->task)->done()->create();

        $this->assertSame(25, $this->task->load('subtasks')->progress());
    }

    public function test_サブタスクが無ければ進捗率は_nullになる(): void
    {
        $this->assertNull($this->task->load('subtasks')->progress());
    }
}
