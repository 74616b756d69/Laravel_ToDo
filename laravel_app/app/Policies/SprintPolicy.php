<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;

/**
 * スプリントの認可。ProjectPolicy / IssuePolicy と同じく
 * 判定の根拠は project_members.role だけ。
 */
class SprintPolicy
{
    public function view(User $user, Sprint $sprint): bool
    {
        return $sprint->project->hasMember($user);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canManage($user, $project);
    }

    public function update(User $user, Sprint $sprint): bool
    {
        return $this->canManage($user, $sprint->project);
    }

    /**
     * 開始と完了。計画を動かす操作なので update と同じ扱いにする。
     */
    public function transition(User $user, Sprint $sprint): bool
    {
        return $this->canManage($user, $sprint->project);
    }

    public function delete(User $user, Sprint $sprint): bool
    {
        return $sprint->project->isAdmin($user);
    }

    private function canManage(User $user, Project $project): bool
    {
        return in_array(
            $project->roleFor($user),
            [ProjectRole::Admin, ProjectRole::Member],
            strict: true,
        );
    }
}
