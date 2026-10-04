<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;
use App\Models\Worklog;

/**
 * 作業時間の認可。
 *
 * 記録できるのは課題に書き込める人。自分の時間しか記録できない（記録者はログイン中の人）。
 * 消せるのは記録した本人か、プロジェクトの admin（打ち間違いの後始末のため）。
 */
class WorklogPolicy
{
    public function create(User $user, Issue $issue): bool
    {
        return $user->can('update', $issue);
    }

    public function delete(User $user, Worklog $worklog): bool
    {
        return ($worklog->user_id === $user->id && $user->can('update', $worklog->issue))
            || $worklog->issue->project->isAdmin($user);
    }
}
