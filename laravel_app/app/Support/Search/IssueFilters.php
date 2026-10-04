<?php

namespace App\Support\Search;

use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 課題一覧の絞り込み条件（URL のクエリ文字列）。
 *
 * 一覧の画面と CSV エクスポートが同じ条件で同じ課題を引くよう、解釈と適用をここに集める。
 * 画面で見えていたものと、書き出したファイルの中身がずれないようにするため。
 */
final class IssueFilters
{
    /**
     * 並び替えの選択肢。キーはクエリ文字列、値は画面表示のラベル。
     */
    public const SORTS = [
        'latest' => '新しい順',
        'oldest' => '古い順',
        'due_date' => '期限が近い順',
        'priority' => '優先度が高い順',
    ];

    /**
     * @param  array{q: ?string, keyword: ?string, project: ?int, status: ?int, category: ?StatusCategory, priority: ?TaskPriority, tag: ?int, overdue: bool, sort: string}  $values
     */
    private function __construct(
        public readonly array $values,
        public readonly IssueQuery $advanced,
    ) {}

    /**
     * クエリ文字列を検証済みの絞り込み条件に変換する。
     */
    public static function fromRequest(Request $request): self
    {
        $sort = (string) $request->query('sort');

        $values = [
            // 条件式（assignee:me status:進行中 …）。解釈は IssueQuery が受け持つ
            'q' => $request->string('q')->trim()->limit(500, '')->value() ?: null,
            'keyword' => $request->string('keyword')->trim()->value() ?: null,
            'project' => $request->integer('project') ?: null,
            'status' => $request->integer('status') ?: null,
            // 集計カードからの絞り込み。ステータス名ではなくカテゴリで横断する
            'category' => StatusCategory::tryFrom((string) $request->query('category')),
            'priority' => TaskPriority::tryFrom((string) $request->query('priority')),
            'tag' => $request->integer('tag') ?: null,
            'overdue' => $request->boolean('overdue'),
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'latest',
        ];

        return new self($values, IssueQuery::parse($values['q']));
    }

    /**
     * 絞り込みと並び替えを足す。見える範囲（visibleTo）は呼び出し側が先に絞っておくこと。
     *
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    public function apply(Builder $query, User $user): Builder
    {
        $this->advanced->apply($query, $user);

        return $query
            ->search($this->values['keyword'])
            ->inProject($this->values['project'])
            ->status($this->values['status'])
            ->category($this->values['category'])
            ->priority($this->values['priority'])
            ->tagged($this->values['tag'])
            ->when($this->values['overdue'], fn (Builder $query) => $query->overdue())
            ->sorted($this->values['sort']);
    }
}
