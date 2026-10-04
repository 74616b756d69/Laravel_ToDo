<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 課題にコメントが投稿された。操作した人は投稿者（$comment->user）。
 *
 * Comment::$dispatchesEvents から出すので、投稿の経路が増えても漏れない。
 */
class CommentPosted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
    ) {}
}
