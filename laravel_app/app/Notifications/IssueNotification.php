<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * 課題に関する通知の共通部分。
 *
 * 中身は送る時点で配列に写し取り、モデルは抱えない。キューに積んでから
 * 配信までの間に課題が消されたり改名されたりしても、配信が落ちないようにするため。
 * 通知一覧に出す文面も「そのときの状態」を残す（履歴と同じ考え方）。
 *
 * アプリ内通知（database）は同期で書き、メールだけキューに回す。
 * キューのワーカーが止まっていても、画面上の通知は届く。
 */
abstract class IssueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var array{kind: string, issue_id: int, issue_key: string, issue_title: string, actor_name: string, message: string, excerpt: ?string} */
    public readonly array $payload;

    public function __construct(Issue $issue, ?User $actor)
    {
        $issue->loadMissing('project');

        $this->payload = [
            'kind' => $this->kind(),
            'issue_id' => $issue->id,
            'issue_key' => $issue->key(),
            'issue_title' => $issue->title,
            'actor_name' => $actor?->name ?? 'システム',
            'message' => $this->message($actor?->name ?? 'システム'),
            'excerpt' => $this->excerpt(),
        ];
    }

    /** 通知の種類。一覧のアイコンの出し分けに使う */
    abstract protected function kind(): string;

    /** 一覧に出す 1 行の文 */
    abstract protected function message(string $actorName): string;

    /** 文の下に添える抜粋（コメント本文など）。無ければ null */
    protected function excerpt(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    /**
     * 課題を開く URL。キーではなく id の入口を使い、
     * あとでプロジェクトキーが変わってもリンクが切れないようにする。
     */
    protected function issueUrl(): string
    {
        return route('tasks.legacy', $this->payload['issue_id']);
    }
}
