<?php

namespace App\Listeners;

use App\Events\CommentPosted;
use App\Events\IssueAssigned;
use App\Events\IssueCreated;
use App\Events\IssueTransitioned;
use App\Models\Issue;
use App\Models\User;
use App\Notifications\CommentPostedNotification;
use App\Notifications\IssueAssignedNotification;
use App\Notifications\IssueTransitionedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * 課題のイベントを、関係する人への通知に変える。
 *
 * 誰に届けるかの決まり:
 *  - 担当になった本人 … 担当の付け替え・担当者つきの作成
 *  - ウォッチャー     … ステータスの変更・コメント
 *  - 操作した本人には届けない（自分がやったことは知っている）
 *  - 操作した人がいない変更（移行コマンドやシーダー）は通知しない
 */
class SendIssueNotifications
{
    public function handleIssueCreated(IssueCreated $event): void
    {
        $assignee = $event->issue->assignee_id === null ? null : User::find($event->issue->assignee_id);

        $this->notifyAssignee($event->issue, $assignee, $event->actor);
    }

    public function handleIssueAssigned(IssueAssigned $event): void
    {
        $this->notifyAssignee($event->issue, $event->assignee, $event->actor);
    }

    public function handleIssueTransitioned(IssueTransitioned $event): void
    {
        if ($event->actor === null) {
            return;
        }

        Notification::send(
            $event->issue->notifiableWatchers(except: $event->actor),
            new IssueTransitionedNotification($event->issue, $event->actor, $event->from, $event->to),
        );
    }

    public function handleCommentPosted(CommentPosted $event): void
    {
        $comment = $event->comment->loadMissing('issue', 'user');

        if ($comment->user === null) {
            return;
        }

        Notification::send(
            $comment->issue->notifiableWatchers(except: $comment->user),
            new CommentPostedNotification($comment),
        );
    }

    private function notifyAssignee(Issue $issue, ?User $assignee, ?User $actor): void
    {
        if ($actor === null || $assignee === null || $assignee->is($actor)) {
            return;
        }

        $assignee->notify(new IssueAssignedNotification($issue, $actor));
    }
}
