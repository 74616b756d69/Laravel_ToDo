<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Support\Str;

/**
 * ウォッチしている課題にコメントが付いた。
 */
class CommentPostedNotification extends IssueNotification
{
    private readonly string $commentExcerpt;

    public function __construct(Comment $comment)
    {
        $comment->loadMissing('issue', 'user');

        // 文面は親のコンストラクタで組み立てるので、その前に控える
        $this->commentExcerpt = Str::limit((string) $comment->body_text, 120);

        parent::__construct($comment->issue, $comment->user);
    }

    protected function kind(): string
    {
        return 'commented';
    }

    protected function message(string $actorName): string
    {
        return "{$actorName}さんがコメントしました。";
    }

    protected function excerpt(): ?string
    {
        return $this->commentExcerpt;
    }
}
