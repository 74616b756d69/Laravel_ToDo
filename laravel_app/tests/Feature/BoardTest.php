<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_ボードがステータスごとに表示される(): void
    {
        Task::factory()->for($this->user)->create(['title' => '未着手のタスク', 'status' => TaskStatus::Todo]);
        Task::factory()->for($this->user)->create(['title' => '完了のタスク', 'status' => TaskStatus::Done]);

        $lanes = $this->actingAs($this->user)->get(route('board'))->assertOk()->viewData('lanes');

        $this->assertCount(1, $lanes['todo']['tasks']);
        $this->assertCount(0, $lanes['doing']['tasks']);
        $this->assertCount(1, $lanes['done']['tasks']);
    }

    public function test_カードを別レーンへ移動するとステータスが変わる(): void
    {
        $task = Task::factory()->for($this->user)->create([
            'status' => TaskStatus::Todo,
            'completed_at' => null,
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('board.move', $task), [
                'status' => TaskStatus::Done->value,
                'ids' => [$task->id],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'done');

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_完了から戻すと完了日時が消える(): void
    {
        $task = Task::factory()->for($this->user)->completed()->create();

        $this->actingAs($this->user)->patchJson(route('board.move', $task), [
            'status' => TaskStatus::Doing->value,
            'ids' => [$task->id],
        ]);

        $this->assertNull($task->refresh()->completed_at);
    }

    public function test_送った順序どおりに並び順が保存される(): void
    {
        $tasks = Task::factory()->count(3)->for($this->user)->create(['status' => TaskStatus::Todo]);
        $reordered = $tasks->reverse()->values();

        $this->actingAs($this->user)->patchJson(route('board.move', $reordered->first()), [
            'status' => TaskStatus::Todo->value,
            'ids' => $reordered->pluck('id')->all(),
        ])->assertOk();

        $this->assertSame(
            $reordered->pluck('id')->all(),
            Task::orderBy('position')->pluck('id')->all(),
        );
    }

    public function test_他人のタスクの並び順は書き換えられない(): void
    {
        $mine = Task::factory()->for($this->user)->create(['status' => TaskStatus::Todo, 'position' => 0]);
        $others = Task::factory()->for(User::factory())->create(['position' => 99]);

        // 他人の ID を紛れ込ませても無視される
        $this->actingAs($this->user)->patchJson(route('board.move', $mine), [
            'status' => TaskStatus::Todo->value,
            'ids' => [$others->id, $mine->id],
        ])->assertOk();

        $this->assertSame(99, $others->refresh()->position);
        $this->assertSame(0, $mine->refresh()->position);
    }

    public function test_他人のタスクは移動できない(): void
    {
        $others = Task::factory()->for(User::factory())->create();

        $this->actingAs($this->user)
            ->patchJson(route('board.move', $others), [
                'status' => TaskStatus::Done->value,
                'ids' => [$others->id],
            ])
            ->assertForbidden();
    }

    public function test_不正なステータスへは移動できない(): void
    {
        $task = Task::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->patchJson(route('board.move', $task), ['status' => 'unknown', 'ids' => [$task->id]])
            ->assertUnprocessable();
    }
}
