<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 課題が作られた。
 *
 * 課題まわりのイベントはすべて IssueObserver から出す。サービスごとに
 * dispatch を書くと、新しい書き込み経路を足したときに出し忘れるため。
 *
 * ShouldDispatchAfterCommit なので、採番の競合などでロールバックされた
 * 作成では飛ばない（存在しない課題の通知が届かない）。
 */
class IssueCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  User|null  $actor  操作した人。コマンドやシーダーからの変更は null
     */
    public function __construct(
        public readonly Issue $issue,
        public readonly ?User $actor,
    ) {}
}
