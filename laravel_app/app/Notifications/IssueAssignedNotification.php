<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * 「あなたが担当になりました」。
 *
 * 自分の作業が増える知らせなので、これだけはメールでも送る。
 * ステータス変更やコメントまでメールにすると量が多すぎて読まれなくなる。
 */
class IssueAssignedNotification extends IssueNotification
{
    protected function kind(): string
    {
        return 'assigned';
    }

    protected function message(string $actorName): string
    {
        return "{$actorName}さんがあなたを担当者にしました。";
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->payload['issue_key']}] 担当になりました: {$this->payload['issue_title']}")
            ->line($this->payload['message'])
            ->line("{$this->payload['issue_key']} {$this->payload['issue_title']}")
            ->action('課題を開く', $this->issueUrl());
    }
}
