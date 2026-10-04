<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Issue;
use App\Models\User;

/**
 * 添付ファイルの認可。
 *
 * 見る・上げるは課題と同じ（見るのは所属メンバー全員、上げるのは書き込める人）。
 * 消せるのは上げた本人か、プロジェクトの admin（コメントと同じ考え方）。
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return $user->can('view', $attachment->issue);
    }

    public function create(User $user, Issue $issue): bool
    {
        return $user->can('update', $issue);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return ($attachment->user_id === $user->id && $user->can('update', $attachment->issue))
            || $attachment->issue->project->isAdmin($user);
    }
}
