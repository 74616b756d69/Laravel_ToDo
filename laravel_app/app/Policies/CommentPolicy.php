<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;

/**
 * コメントの認可。
 *
 * 読むのは所属メンバー全員。書けるのは admin と member。
 * 消せるのは自分のコメントか、プロジェクトの admin。
 */
class CommentPolicy
{
    public function create(User $user, Issue $issue): bool
    {
        return in_array(
            $issue->project->roleFor($user),
            [ProjectRole::Admin, ProjectRole::Member],
            strict: true,
        );
    }

    /**
     * 編集できるのは書いた本人だけ。
     * admin でも他人の発言を書き換えさせない（議論の記録が信用できなくなる）。
     */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id
            && $this->create($user, $comment->issue);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id
            || $comment->issue->project->isAdmin($user);
    }
}
