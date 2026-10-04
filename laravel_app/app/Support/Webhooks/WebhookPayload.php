<?php

namespace App\Support\Webhooks;

use App\Enums\WebhookEvent;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Webhook で送る中身を組み立てる。
 *
 * 通知（IssueNotification）と同じく、イベントの時点の値を配列に写し取る。
 * 再試行や再送のときに課題が改名・削除されていても、同じものが届くようにするため。
 */
class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function forIssue(WebhookEvent $event, Issue $issue, ?User $actor, array $extra = []): array
    {
        $issue->loadMissing('project', 'status', 'assignee');

        return [
            'event' => $event->value,
            'occurred_at' => now()->toIso8601String(),
            'project' => self::project($issue->project),
            'actor' => self::user($actor),
            'issue' => [
                'id' => $issue->id,
                'key' => $issue->key(),
                'title' => $issue->title,
                'url' => route('tasks.legacy', $issue->id),
                'type' => $issue->issue_type?->value,
                'priority' => $issue->priority?->value,
                'status' => $issue->status->name,
                'assignee' => self::user($issue->assignee),
            ],
            ...$extra,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transitioned(Issue $issue, ?Status $from, Status $to, ?User $actor): array
    {
        return self::forIssue(WebhookEvent::IssueTransitioned, $issue, $actor, [
            'changes' => ['status' => ['from' => $from?->name, 'to' => $to->name]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function commented(Comment $comment): array
    {
        $comment->loadMissing('issue', 'user');

        return self::forIssue(WebhookEvent::CommentCreated, $comment->issue, $comment->user, [
            'comment' => [
                'id' => $comment->id,
                'excerpt' => Str::limit((string) $comment->body_text, 280),
            ],
        ]);
    }

    /**
     * 疎通確認。課題を持たない。
     *
     * @return array<string, mixed>
     */
    public static function ping(Project $project, User $actor): array
    {
        return [
            'event' => WebhookEvent::Ping->value,
            'occurred_at' => now()->toIso8601String(),
            'project' => self::project($project),
            'actor' => self::user($actor),
        ];
    }

    /**
     * Slack に流す 1 行。Slack の書式（mrkdwn）で、課題キーをリンクにする。
     *
     * @param  array<string, mixed>  $payload
     */
    public static function slackText(array $payload): string
    {
        $actor = self::escape($payload['actor']['name'] ?? 'システム');

        if (($payload['event'] ?? null) === WebhookEvent::Ping->value) {
            return "Tracklet の Webhook が {$payload['project']['key']} につながりました（{$actor} さんがテスト送信）。";
        }

        $issue = $payload['issue'];
        $link = '<'.$issue['url'].'|'.self::escape("{$issue['key']} {$issue['title']}").'>';

        return match (WebhookEvent::tryFrom($payload['event'])) {
            WebhookEvent::IssueCreated => "*{$actor}* さんが {$link} を作成しました。",
            WebhookEvent::IssueTransitioned => "*{$actor}* さんが {$link} のステータスを「"
                .self::escape($payload['changes']['status']['from'] ?? '—').'」→「'
                .self::escape($payload['changes']['status']['to']).'」に変更しました。',
            WebhookEvent::IssueAssigned => $issue['assignee'] === null
                ? "*{$actor}* さんが {$link} の担当者を外しました。"
                : "*{$actor}* さんが {$link} の担当者を *".self::escape($issue['assignee']['name']).'* さんにしました。',
            WebhookEvent::CommentCreated => "*{$actor}* さんが {$link} にコメントしました。\n>"
                .self::escape($payload['comment']['excerpt']),
            default => "*{$actor}* さんが {$link} を更新しました。",
        };
    }

    /**
     * @return array{key: string, name: string}
     */
    private static function project(Project $project): array
    {
        return ['key' => $project->key, 'name' => $project->name];
    }

    /**
     * メールアドレスは送らない。送り先は外部サービスなので、渡すのは名前と id だけ。
     *
     * @return array{id: int, name: string}|null
     */
    private static function user(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }

    /**
     * Slack の制御文字（& < >）を逃がす。課題名に <!channel> と書いて全員に通知を飛ばす、を防ぐ。
     */
    private static function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}
