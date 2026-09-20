<?php

namespace App\Observers;

use App\Enums\ActivityField;
use App\Enums\TaskPriority;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\User;
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
 */
class IssueObserver
{
    public function created(Issue $issue): void
    {
        $this->record($issue, ActivityField::Created, null, null);
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
