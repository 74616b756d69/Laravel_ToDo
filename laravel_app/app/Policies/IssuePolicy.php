<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

/**
 * 課題の認可。判定の根拠は project_members.role だけに置く。
 *
 * 「どの課題が見えるか」は Policy では守れないので、
 * Issue::scopeVisibleTo() が担当する。両者が同じ根拠を見ていることが大事。
 */
class IssuePolicy
{
    /**
     * 所属していれば誰でも見られる（viewer を含む）。
     */
    public function view(User $user, Issue $issue): bool
    {
        return $issue->project->hasMember($user);
    }

    /**
     * 作成はプロジェクト単位の判断なので、対象の課題ではなくプロジェクトを受け取る。
     */
    public function create(User $user, Project $project): bool
    {
        return $this->canWrite($user, $project);
    }

    public function update(User $user, Issue $issue): bool
    {
        return $this->canWrite($user, $issue->project);
    }

    /**
     * 削除は admin か、起票した本人だけ。
     * 他人が立てた課題を member が勝手に消せないようにする。
     */
    public function delete(User $user, Issue $issue): bool
    {
        $role = $issue->project->roleFor($user);

        return $role === ProjectRole::Admin
            || ($role === ProjectRole::Member && $issue->reporter_id === $user->id);
    }

    /**
     * 書き込み権限があるか（viewer は読むだけ）。
     */
    private function canWrite(User $user, Project $project): bool
    {
        return in_array(
            $project->roleFor($user),
            [ProjectRole::Admin, ProjectRole::Member],
            strict: true,
        );
    }
}
