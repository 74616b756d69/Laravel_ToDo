<?php

namespace Tests\Concerns;

use App\Models\Project;
use App\Models\Status;
use App\Models\User;

/**
 * ステータスがプロジェクトごとの行になったので、テストから名前で引けるようにする。
 */
trait UsesWorkflow
{
    protected function statusFor(User $user, string $name): Status
    {
        return Project::personalFor($user)->statuses()->where('name', $name)->sole();
    }

    protected function statusIdFor(User $user, string $name): int
    {
        return $this->statusFor($user, $name)->id;
    }
}
