<?php

namespace App\Services;

use App\Enums\IssueLinkType;
use App\Exceptions\IssueLinkException;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Support\IssueReference;
use Illuminate\Support\Collection;

/**
 * 課題どうしの関連。
 *
 * 親子（IssueHierarchyService）と違い、相手の居場所は変えない。
 * 関連づけても一覧・ボード・バックログからは消えない。
 */
class IssueLinkService
{
    /**
     * 入力（課題キーまたは URL）から相手を引いて関連づける。
     */
    public function linkFrom(Issue $source, string $input, IssueLinkType $type): IssueLink
    {
        $input = trim($input);

        $target = IssueReference::resolve($input, $source->project)
            ?? throw IssueLinkException::notFound($input);

        return $this->link($source, $target, $type);
    }

    public function link(Issue $source, Issue $target, IssueLinkType $type): IssueLink
    {
        if ($source->is($target)) {
            throw IssueLinkException::itself();
        }

        $existing = $this->findExisting($source, $target, $type);

        if ($existing !== null) {
            throw IssueLinkException::alreadyLinked($target->key(), $existing->labelFor($source));
        }

        return IssueLink::create([
            'source_issue_id' => $source->id,
            'target_issue_id' => $target->id,
            'type' => $type,
        ]);
    }

    public function unlink(IssueLink $link): void
    {
        $link->delete();
    }

    /**
     * 同じ関係がすでにあるか。
     *
     * 「関連」は向きが無いので、逆向きに張られていても同じものとして扱う。
     * 向きのある種別は、逆向きなら別の関係（A が B をブロック / B が A をブロック）。
     */
    private function findExisting(Issue $source, Issue $target, IssueLinkType $type): ?IssueLink
    {
        $query = IssueLink::where('type', $type)->where(function ($query) use ($source, $target) {
            $query->where(fn ($q) => $q
                ->where('source_issue_id', $source->id)
                ->where('target_issue_id', $target->id));
        });

        if ($type->isSymmetric()) {
            $query->orWhere(fn ($q) => $q
                ->where('type', $type)
                ->where('source_issue_id', $target->id)
                ->where('target_issue_id', $source->id));
        }

        return $query->first();
    }

    /**
     * 詳細画面に出す形。読み方ごとにまとめる。
     *
     * 「が関連: A, B」「をブロック: C」のように、Jira と同じ並べ方をする。
     *
     * @return Collection<string, Collection<int, array{link: IssueLink, issue: Issue}>>
     */
    public function groupedFor(Issue $issue): Collection
    {
        // 節の並びは種別の宣言順に固定する。読み込むたびに
        // 「が関連」と「をブロック」が入れ替わると落ち着かない
        $order = array_flip(array_column(IssueLinkType::cases(), 'value'));

        return $issue->links()
            ->map(fn (IssueLink $link) => [
                'label' => $link->labelFor($issue),
                'link' => $link,
                'issue' => $link->counterpartFor($issue),
                // 同じ種別なら、張った側の読み方を先に出す
                'order' => [$order[$link->type->value], $link->source_issue_id === $issue->id ? 0 : 1],
            ])
            ->sortBy(fn (array $row) => $row['order'])
            ->groupBy('label')
            ->map(fn (Collection $rows) => $rows->sortBy('issue.issue_number')->values());
    }
}
