<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\Status;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 課題のステータスが変わった。
 *
 * ステータスの書き込みは WorkflowService::transition() だけが行うので、
 * このイベントが出たときは遷移の検査を通っている。
 */
class IssueTransitioned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Issue $issue,
        public readonly ?Status $from,
        public readonly Status $to,
        public readonly ?User $actor,
    ) {}
}
