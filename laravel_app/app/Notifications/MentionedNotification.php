<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 「あなたがメンションされました」。
 *
 * 名指しで呼ばれた知らせなので、担当と同じくメールでも送る。
 */
class MentionedNotification extends IssueNotification
{
    /**
     * @param  'comment'|'description'  $source
     */
    public function __construct(
        Issue $issue,
        ?User $actor,
        private readonly string $source,
        private readonly ?string $mentionExcerpt,
    ) {
        parent::__construct($issue, $actor);
    }

    protected function kind(): string
    {
        return 'mentioned';
    }

    protected function message(string $actorName): string
    {
        return $this->source === 'comment'
            ? "{$actorName}さんがコメントであなたをメンションしました。"
            : "{$actorName}さんが説明であなたをメンションしました。";
    }

    protected function excerpt(): ?string
    {
        return $this->mentionExcerpt;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->payload['issue_key']}] メンションされました: {$this->payload['issue_title']}")
            ->line($this->payload['message'])
            ->when($this->payload['excerpt'], fn (MailMessage $mail, string $excerpt) => $mail->line("「{$excerpt}」"))
            ->action('課題を開く', $this->issueUrl());
    }
}
