<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 他人のタスクに一切アクセスできないことを担保する。
 */
class TaskAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Task $othersTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->othersTask = Task::factory()->for(User::factory())->create();
    }

    public function test_他人のタスクは閲覧できない(): void
    {
        $this->actingAs($this->user)
            ->get(route('tasks.show', $this->othersTask))
            ->assertForbidden();
    }

    public function test_他人のタスクは編集画面を開けない(): void
    {
        $this->actingAs($this->user)
            ->get(route('tasks.edit', $this->othersTask))
            ->assertForbidden();
    }

    public function test_他人のタスクは更新できない(): void
    {
        $this->actingAs($this->user)->put(route('tasks.update', $this->othersTask), [
            'title' => '乗っ取り',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Low->value,
        ])->assertForbidden();

        $this->assertNotSame('乗っ取り', $this->othersTask->refresh()->title);
    }

    public function test_他人のタスクは削除できない(): void
    {
        $this->actingAs($this->user)
            ->delete(route('tasks.destroy', $this->othersTask))
            ->assertForbidden();

        $this->assertNotSoftDeleted($this->othersTask);
    }

    public function test_他人のタスクは完了トグルできない(): void
    {
        $this->actingAs($this->user)
            ->patch(route('tasks.completion', $this->othersTask))
            ->assertForbidden();
    }
}
