<?php

namespace App\Listeners;

use App\Enums\WebhookEvent;
use App\Events\CommentPosted;
use App\Events\IssueAssigned;
use App\Events\IssueCreated;
use App\Events\IssueTransitioned;
use App\Services\WebhookDispatcher;
use App\Support\Webhooks\WebhookPayload;

/**
 * 課題のイベントを、プロジェクトに登録された Webhook へ流す。
 *
 * 通知（SendIssueNotifications）と同じイベントに乗せているので、
 * 書き込みの経路が増えても Webhook だけ漏れる、ということが起きない。
 * 操作した人がいない変更（移行コマンドやシーダー）は、通知と同じく流さない。
 */
class DispatchWebhooks
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
    ) {}

    public function handleIssueCreated(IssueCreated $event): void
    {
        if ($event->actor === null) {
            return;
        }

        $this->dispatcher->broadcast(
            $event->issue->project_id,
            WebhookEvent::IssueCreated,
            WebhookPayload::forIssue(WebhookEvent::IssueCreated, $event->issue, $event->actor),
        );
    }

    public function handleIssueTransitioned(IssueTransitioned $event): void
    {
        if ($event->actor === null) {
            return;
        }

        $this->dispatcher->broadcast(
            $event->issue->project_id,
            WebhookEvent::IssueTransitioned,
            WebhookPayload::transitioned($event->issue, $event->from, $event->to, $event->actor),
        );
    }

    public function handleIssueAssigned(IssueAssigned $event): void
    {
        if ($event->actor === null) {
            return;
        }

        $this->dispatcher->broadcast(
            $event->issue->project_id,
            WebhookEvent::IssueAssigned,
            WebhookPayload::forIssue(WebhookEvent::IssueAssigned, $event->issue, $event->actor),
        );
    }

    public function handleCommentPosted(CommentPosted $event): void
    {
        $comment = $event->comment->loadMissing('issue', 'user');

        if ($comment->user === null) {
            return;
        }

        $this->dispatcher->broadcast(
            $comment->issue->project_id,
            WebhookEvent::CommentCreated,
            WebhookPayload::commented($comment),
        );
    }
}
