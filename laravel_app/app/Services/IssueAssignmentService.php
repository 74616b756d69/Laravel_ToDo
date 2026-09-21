<?php

namespace App\Services;

use App\Exceptions\IssueAssignmentException;
use App\Models\Issue;
use App\Models\User;

/**
 * 担当者の付け替え。
 *
 * assignee_id は外部キーなので Issue::$fillable には無い。
 * 「プロジェクトに居る人しか担当者になれない」の判断を 1 箇所に置くため、
 * 書き込みはここだけを通す（詳細画面のワンクリックも、編集フォームも）。
 *
 * 保存をモデル経由にしているのは、IssueObserver が履歴を残せるようにするため。
 * クエリビルダの一括 update に置き換えないこと。
 */
class IssueAssignmentService
{
    /**
     * 担当者を差し替える。null は未割り当て。
     */
    public function assign(Issue $issue, ?User $assignee): Issue
    {
        if ($assignee !== null && ! $issue->project->hasMember($assignee)) {
            throw IssueAssignmentException::notAMember($assignee->name, $issue->project->name);
        }

        $issue->forceFill(['assignee_id' => $assignee?->id])->save();

        return $issue->setRelation('assignee', $assignee);
    }
}
