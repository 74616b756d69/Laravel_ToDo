<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * プロジェクトの認可。判定の根拠は project_members.role だけに置く。
 *
 * 一覧（「どのプロジェクトが見えるか」）は Policy では守れないので、
 * Project::scopeVisibleTo() が担当する。
 */
class ProjectPolicy
{
    /**
     * 所属していれば誰でも見られる（viewer を含む）。
     */
    public function view(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->isAdmin($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->isAdmin($user);
    }
}
