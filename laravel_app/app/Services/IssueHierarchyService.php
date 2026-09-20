<?php

namespace App\Services;

use App\Enums\IssueType;
use App\Enums\TaskPriority;
use App\Exceptions\IssueHierarchyException;
use App\Models\Issue;
use App\Support\IssueReference;

/**
 * 課題の親子関係。
 *
 * 「サブタスクかどうか」は issue_type ではなく parent_id で決まる。
 * 種別（Bug / Story / Task）は課題の性質を表すものなので、
 * 親の下に入れたからといって書き換えない。
 *
 * 階層は 1 段までに制限している。深くできるようにすると、
 * 進捗の集計もボードの表示も一気に難しくなるわりに、
 * 最小構成で必要になる場面がない。
 */
class IssueHierarchyService
{
    /**
     * サブタスク欄の 1 行を受けて、既存課題を紐づけるか新しく作るかを決める。
     *
     * 「PROJ-12」や詳細画面の URL を指していれば紐づけ、
     * それ以外はこれまでどおりタイトルとして新規作成する。
     */
    public function addFrom(Issue $parent, string $input, int $reporterId): Issue
    {
        $input = trim($input);

        if (! IssueReference::looksLikeReference($input)) {
            return $this->createChild($parent, $input, $reporterId);
        }

        // 課題を指しているつもりの入力は、解決できなくても
        // タイトルとして新規作成しない（打ち間違いを黙って課題に変えない）
        $child = IssueReference::resolve($input, $parent->project)
            ?? throw IssueHierarchyException::notFound($input);

        return $this->attach($parent, $child);
    }

    /**
     * 既存の課題を親の下に入れる。
     */
    public function attach(Issue $parent, Issue $child): Issue
    {
        $this->assertCanAttach($parent, $child);

        $child->forceFill([
            'parent_id' => $parent->id,
            // 並びは親の中での末尾に置く
            'position' => (int) $parent->children()->max('position') + 1,
        ])->save();

        return $child;
    }

    /**
     * 親子を外す。課題そのものは残り、一覧やボードに戻ってくる。
     */
    public function detach(Issue $child): Issue
    {
        $child->forceFill(['parent_id' => null])->save();

        return $child;
    }

    /**
     * タイトルだけの軽いサブタスクを新しく作る。
     */
    private function createChild(Issue $parent, string $title, int $reporterId): Issue
    {
        $this->assertCanHaveChildren($parent);

        return $parent->project->createIssue([
            'title' => $title,
            'issue_type' => IssueType::Subtask,
            'parent_id' => $parent->id,
            'status_id' => $parent->project->initialStatus()->id,
            'priority' => TaskPriority::Medium,
            'reporter_id' => $reporterId,
            // 親の担当者を引き継ぐ。誰の持ち物か分からない子課題を作らない
            'assignee_id' => $parent->assignee_id,
            'position' => (int) $parent->children()->max('position') + 1,
        ]);
    }

    /**
     * 紐づけてよい組み合わせか。
     *
     * 階層を 1 段に保てば、循環参照は原理的に起きない。
     * 「親は子を持てない」「子は子を持っていない」の 2 つが同時に成り立つ状況は、
     * A→B と B→A のどちらか一方を張った時点で消えるため。
     */
    private function assertCanAttach(Issue $parent, Issue $child): void
    {
        if ($parent->is($child)) {
            throw IssueHierarchyException::itself();
        }

        if ($child->parent_id === $parent->id) {
            throw IssueHierarchyException::alreadyAttached($child->key(), $parent->key());
        }

        $this->assertCanHaveChildren($parent);

        $grandchildren = $child->children()->count();

        if ($grandchildren > 0) {
            throw IssueHierarchyException::childHasChildren($child->key(), $grandchildren);
        }
    }

    /**
     * その課題が子を持てるか（＝自身が子でないか）。
     */
    private function assertCanHaveChildren(Issue $parent): void
    {
        if ($parent->parent_id !== null) {
            throw IssueHierarchyException::parentIsChild($parent->key());
        }
    }
}
