<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 課題の担当者が変わった。$assignee が null なら未割り当てに戻した。
 */
class IssueAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Issue $issue,
        public readonly ?User $assignee,
        public readonly ?User $actor,
    ) {}
}
