<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * 他人のタスクには一切触れさせない。view / update / delete で条件は同じ。
     */
    public function view(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    private function owns(User $user, Task $task): bool
    {
        return $user->id === $task->user_id;
    }
}
