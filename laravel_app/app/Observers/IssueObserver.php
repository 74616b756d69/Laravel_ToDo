<?php

namespace App\Observers;

use App\Enums\ActivityField;
use App\Enums\TaskPriority;
use App\Events\IssueAssigned;
use App\Events\IssueChanged;
use App\Events\IssueCreated;
use App\Events\IssueTransitioned;
use App\Events\UsersMentioned;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\User;
use App\Support\Duration;
use App\Support\Mentions;
use Illuminate\Support\Facades\Auth;

/**
 * 課題の変更を履歴に残す。
 *
 * 記録漏れを防ぐうえで大事なのは 2 点:
 *  - 追跡対象のカラムは ActivityField::trackedColumns() の 1 箇所で決める
 *  - 書き込みは必ずモデル経由にする（クエリビルダの一括 update はイベントが飛ばない）
 *
 * 値は ID ではなく「そのときの表示名」を残す。スプリントや担当者が
 * あとから消えても履歴が読めるようにするため。
 *
 * 通知や外部連携の起点になるドメインイベント（App\Events\Issue*）もここから出す。
 * 履歴と同じ「モデルが変わったら必ず通る場所」に置けば、記録とイベントの
 * どちらか片方だけが漏れる、ということが起きない。
 */
class IssueObserver
{
    /** ほかの人の画面へ知らせる価値のある変更 */
    private const BROADCAST_COLUMNS = [
        'title', 'content', 'status_id', 'assignee_id', 'priority', 'issue_type',
        'sprint_id', 'due_date', 'story_points', 'parent_id',
    ];

    public function deleted(Issue $issue): void
    {
        $this->broadcast($issue, 'deleted');
    }

    /**
     * 開いている画面へ知らせる。操作した人がいない変更（シーダー・移行・取り込み）は流さない
     * （何百件ぶんの通知が一度に飛ぶのを避ける。通知や Webhook と同じ扱い）。
     *
     * @param  'created'|'updated'|'deleted'  $action
     */
    private function broadcast(Issue $issue, string $action): void
    {
        $actor = Auth::user();

        if ($actor !== null) {
            IssueChanged::dispatch($issue, $action, $actor);
        }
    }

    public function created(Issue $issue): void
    {
        $this->record($issue, ActivityField::Created, null, null);

        IssueCreated::dispatch($issue, Auth::user());
        $this->broadcast($issue, 'created');

        $this->dispatchMentions($issue, Mentions::extractIds($issue->content));
    }

    public function updated(Issue $issue): void
    {
        foreach (ActivityField::trackedColumns() as $column => $field) {
            if (! $issue->wasChanged($column)) {
                continue;
            }

            $this->record(
                $issue,
                $field,
                $this->label($field, $issue->getOriginal($column)),
                $this->label($field, $issue->getAttribute($column)),
            );
        }

        $this->dispatchEvents($issue);

        // 並べ替え（position だけの変更）は流さない。1 回のドラッグで何十件も動くうえ、
        // ほかの人の画面の並びを勝手に変える必要もない
        if ($issue->wasChanged(self::BROADCAST_COLUMNS)) {
            $this->broadcast($issue, 'updated');
        }

        if ($issue->wasChanged('content')) {
            $this->dispatchMentions($issue, array_values(array_diff(
                Mentions::extractIds($issue->content),
                Mentions::extractIds($issue->getOriginal('content')),
            )));
        }
    }

    /**
     * 説明で新しく呼ばれた人に知らせる。
     *
     * @param  list<int>  $userIds
     */
    private function dispatchMentions(Issue $issue, array $userIds): void
    {
        if ($userIds !== []) {
            UsersMentioned::dispatch($issue, $userIds, Auth::user(), 'description', $issue->excerpt());
        }
    }

    /**
     * 通知に値する変更だけをイベントにする。
     *
     * 関連（$issue->status など）はサービスが保存後に差し替えるので、
     * ここで読むと古い値のことがある。ID から引き直す。
     */
    private function dispatchEvents(Issue $issue): void
    {
        if ($issue->wasChanged('status_id')) {
            IssueTransitioned::dispatch(
                $issue,
                Status::find($issue->getOriginal('status_id')),
                Status::findOrFail($issue->status_id),
                Auth::user(),
            );
        }

        if ($issue->wasChanged('assignee_id')) {
            IssueAssigned::dispatch(
                $issue,
                $issue->assignee_id === null ? null : User::find($issue->assignee_id),
                Auth::user(),
            );
        }
    }

    /**
     * 生の値を、そのとき画面に出ていた文字列へ変換する。
     */
    private function label(ActivityField $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            ActivityField::Status => Status::find($value)?->name,
            ActivityField::Assignee => User::find($value)?->name,
            ActivityField::Sprint => Sprint::find($value)?->name,
            ActivityField::Estimate => Duration::format((int) $value),
            // 優先度は enum。キャスト前後のどちらで来ても受けられるようにする
            ActivityField::Priority => is_string($value)
                ? TaskPriority::tryFrom($value)?->label()
                : $value->label(),
            default => (string) $value,
        };
    }

    private function record(Issue $issue, ActivityField $field, ?string $old, ?string $new): void
    {
        // 表示名が引けず、どちらも空になったなら記録する意味がない
        if ($field !== ActivityField::Created && $old === null && $new === null) {
            return;
        }

        Activity::create([
            'issue_id' => $issue->id,
            // コマンドやシーダーからの変更は誰のものでもないので null
            'user_id' => Auth::id(),
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
