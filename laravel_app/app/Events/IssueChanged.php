<?php

namespace App\Events;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 課題が作られた・変わった・消えた、を同じプロジェクトを開いている人の画面へ知らせる（Reverb）。
 *
 * 中身は「何番の課題が誰によって」だけ。課題の内容は送らず、受けた画面が
 * 自分の権限で読み直す。チャンネルの認可を間違えても、中身までは漏れないようにするため。
 *
 * 送るのはキュー経由（ShouldBroadcast）。Reverb が落ちていても、操作そのものは遅くならない。
 */
class IssueChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public readonly int $projectId;

    public readonly int $issueId;

    public readonly string $issueKey;

    public readonly int $actorId;

    public readonly string $actorName;

    /**
     * @param  'created'|'updated'|'deleted'  $action
     */
    public function __construct(Issue $issue, public readonly string $action, User $actor)
    {
        $issue->loadMissing('project');

        $this->projectId = $issue->project_id;
        $this->issueId = $issue->id;
        $this->issueKey = $issue->key();
        $this->actorId = $actor->id;
        $this->actorName = $actor->name;
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("projects.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'issue.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'issue_id' => $this->issueId,
            'issue_key' => $this->issueKey,
            // 自分の操作で自分の画面が読み直されないよう、受け手が見分けるのに使う
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
        ];
    }
}
