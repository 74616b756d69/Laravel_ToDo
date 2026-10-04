<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\Status;
use App\Models\User;

/**
 * ウォッチしている課題のステータスが変わった。
 */
class IssueTransitionedNotification extends IssueNotification
{
    private readonly ?string $fromName;

    private readonly string $toName;

    public function __construct(Issue $issue, ?User $actor, ?Status $from, Status $to)
    {
        // 文面は親のコンストラクタで組み立てるので、その前に名前を控える
        $this->fromName = $from?->name;
        $this->toName = $to->name;

        parent::__construct($issue, $actor);
    }

    protected function kind(): string
    {
        return 'transitioned';
    }

    protected function message(string $actorName): string
    {
        return $this->fromName === null
            ? "{$actorName}さんがステータスを「{$this->toName}」にしました。"
            : "{$actorName}さんがステータスを「{$this->fromName}」から「{$this->toName}」に変更しました。";
    }
}
