<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use App\Models\Task;
use Tests\TestCase;

class TaskTest extends TestCase
{
    public function test_未完了で期限を過ぎていれば期限切れになる(): void
    {
        $task = new Task(['status' => TaskStatus::Todo, 'due_date' => today()->subDay()]);

        $this->assertTrue($task->isOverdue());
    }

    public function test_完了済みなら期限を過ぎていても期限切れにしない(): void
    {
        $task = new Task(['status' => TaskStatus::Done, 'due_date' => today()->subDay()]);

        $this->assertFalse($task->isOverdue());
    }

    public function test_期限が未設定なら期限切れにならない(): void
    {
        $task = new Task(['status' => TaskStatus::Todo, 'due_date' => null]);

        $this->assertFalse($task->isOverdue());
        $this->assertFalse($task->isDueSoon());
    }

    public function test_期限が3日以内なら間近と判定する(): void
    {
        $task = new Task(['status' => TaskStatus::Todo, 'due_date' => today()->addDay()]);

        $this->assertTrue($task->isDueSoon());
    }
}
