<?php

namespace App\Enums;

/**
 * Webhook で受け取れる出来事。
 *
 * 値は外部へ送る名前（X-Tracklet-Event ヘッダと本文の event）なので、
 * 相手側の実装を壊さないよう、一度出した値は変えないこと。
 */
enum WebhookEvent: string
{
    case IssueCreated = 'issue.created';
    case IssueTransitioned = 'issue.transitioned';
    case IssueAssigned = 'issue.assigned';
    case CommentCreated = 'comment.created';

    /** 疎通確認。購読の設定とは関係なく、テスト送信のときだけ送る */
    case Ping = 'ping';

    public function label(): string
    {
        return match ($this) {
            self::IssueCreated => '課題の作成',
            self::IssueTransitioned => 'ステータスの変更',
            self::IssueAssigned => '担当者の変更',
            self::CommentCreated => 'コメントの投稿',
            self::Ping => 'テスト送信',
        };
    }

    /**
     * 購読の選択肢（ping は選ばせない）。
     *
     * @return list<self>
     */
    public static function subscribable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $event) => $event !== self::Ping));
    }
}
