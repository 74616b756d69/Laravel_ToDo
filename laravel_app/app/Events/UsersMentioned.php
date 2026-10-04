<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 課題の説明かコメントで、誰かが @メンションされた。
 *
 * $userIds は「今回の保存で新しく増えた」人だけ。書き直しのたびに、
 * 前から書いてあった人へ同じ通知が飛ばないようにするため。
 * プロジェクトのメンバーかどうかは保存前（Mentions::normalize()）に確かめてある。
 */
class UsersMentioned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $userIds
     * @param  'comment'|'description'  $source
     */
    public function __construct(
        public readonly Issue $issue,
        public readonly array $userIds,
        public readonly ?User $actor,
        public readonly string $source,
        public readonly ?string $excerpt,
    ) {}
}
