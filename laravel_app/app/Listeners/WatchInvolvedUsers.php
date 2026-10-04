<?php

namespace App\Listeners;

use App\Events\CommentPosted;
use App\Events\IssueAssigned;
use App\Events\IssueCreated;
use App\Models\Issue;

/**
 * 課題に関わった人を自動でウォッチに加える。
 *
 * 起票した・担当になった・コメントした人は、その後の動きを知りたいはず。
 * 自分でウォッチを押させると、押し忘れた人にだけ情報が届かない。
 * 外したい人は詳細画面から外せる（外したあとでまた関われば、また加わる）。
 */
class WatchInvolvedUsers
{
    public function handleIssueCreated(IssueCreated $event): void
    {
        $this->watch($event->issue, $event->issue->reporter_id, $event->issue->assignee_id);
    }

    public function handleIssueAssigned(IssueAssigned $event): void
    {
        $this->watch($event->issue, $event->assignee?->id);
    }

    public function handleCommentPosted(CommentPosted $event): void
    {
        $this->watch($event->comment->issue()->firstOrFail(), $event->comment->user_id);
    }

    private function watch(Issue $issue, ?int ...$userIds): void
    {
        $ids = array_values(array_filter($userIds));

        if ($ids !== []) {
            $issue->watchers()->syncWithoutDetaching($ids);
        }
    }
}
