<?php

namespace App\Support\Search;

use App\Enums\IssueType;
use App\Enums\SprintState;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * 一覧の検索窓に書く条件式。
 *
 *     assignee:me status:進行中 due<7d -tag:後回し ログイン
 *
 * 「項目:値」「項目<値」の組と、それ以外の言葉（タイトル・本文の部分一致）を AND でつなぐ。
 * 頭に - を付けた組は否定になる。値に空白を含めるときは "…" で囲む。
 *
 * 解釈できなかった組は黙って捨てずに errors() で返し、画面に出す。
 * 条件が 1 つ黙って外れると、利用者は「絞れている」と思い込んだまま結果を読んでしまう。
 *
 * 見える範囲（visibleTo）はここでは扱わない。呼び出し側が先に絞ったクエリに足すだけ。
 */
class IssueQuery
{
    /**
     * 使える項目。キーは書き方（日本語の別名を含む）、値は内部の名前。
     */
    private const FIELDS = [
        'status' => 'status', 'ステータス' => 'status',
        'is' => 'is',
        'assignee' => 'assignee', '担当' => 'assignee', '担当者' => 'assignee',
        'reporter' => 'reporter', '起票者' => 'reporter',
        'priority' => 'priority', '優先度' => 'priority',
        'type' => 'type', 'タイプ' => 'type',
        'tag' => 'tag', 'タグ' => 'tag',
        'project' => 'project', 'プロジェクト' => 'project',
        'sprint' => 'sprint', 'スプリント' => 'sprint',
        'due' => 'due', '期限' => 'due',
        'created' => 'created', '作成' => 'created',
        'updated' => 'updated', '更新' => 'updated',
    ];

    /** 大小を比べられる（< > が使える）項目 */
    private const DATE_FIELDS = ['due', 'created', 'updated'];

    /** @var list<array{field: string, op: string, value: string, negate: bool, raw: string}> */
    private array $clauses = [];

    /** @var list<string> */
    private array $words = [];

    /** @var list<string> */
    private array $errors = [];

    private function __construct(
        public readonly string $raw,
    ) {}

    public static function parse(?string $input): self
    {
        $query = new self(trim((string) $input));

        // -?項目 演算子 値（"…" で囲めば空白を含められる）| "…" | ひとかたまりの言葉
        preg_match_all(
            '/(?<neg>-)?(?<field>\p{L}[\p{L}\p{N}_]*)(?<op><=|>=|:|<|>|=)(?:"(?<qv>[^"]*)"|(?<v>\S+))|"(?<qw>[^"]+)"|(?<w>\S+)/u',
            $query->raw,
            $matches,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );

        foreach ($matches as $match) {
            if ($match['field'] === null) {
                $query->words[] = $match['qw'] ?? $match['w'];

                continue;
            }

            $query->addClause(
                mb_strtolower($match['field']),
                $match['op'],
                $match['qv'] ?? $match['v'],
                $match['neg'] !== null,
                $match[0],
            );
        }

        return $query;
    }

    /**
     * 条件式に見えるか。ヘッダーの検索窓が、キーワード検索とどちらへ流すかを決めるのに使う。
     */
    public static function looksLikeQuery(string $input): bool
    {
        $fields = implode('|', array_map(fn (string $field) => preg_quote($field, '/'), array_keys(self::FIELDS)));

        return (bool) preg_match("/(^|\\s)-?({$fields})(<=|>=|:|<|>|=)\\S/iu", $input);
    }

    public function isEmpty(): bool
    {
        return $this->clauses === [] && $this->words === [];
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param  Builder<Issue>  $query
     */
    public function apply(Builder $query, User $user): void
    {
        foreach ($this->words as $word) {
            $query->search($word);
        }

        foreach ($this->clauses as $clause) {
            $apply = fn (Builder $query) => $this->applyClause($query, $clause, $user);

            $clause['negate'] ? $query->whereNot($apply) : $query->where($apply);
        }
    }

    private function addClause(string $field, string $op, string $value, bool $negate, string $raw): void
    {
        $name = self::FIELDS[$field] ?? null;

        // 貼り付けた URL（https://…）は条件ではなく、ただの言葉として探す
        if ($name === null && str_starts_with($value, '//')) {
            $this->words[] = $raw;

            return;
        }

        if ($name === null) {
            $this->errors[] = "{$raw}: 「{$field}」という項目はありません。";

            return;
        }

        if ($op === '=') {
            $op = ':';
        }

        if ($op !== ':' && ! in_array($name, self::DATE_FIELDS, true)) {
            $this->errors[] = "{$raw}: 「{$field}」には大小の比較（< >）が使えません。";

            return;
        }

        $error = $this->validate($name, $op, $value);

        if ($error !== null) {
            $this->errors[] = "{$raw}: {$error}";

            return;
        }

        $this->clauses[] = ['field' => $name, 'op' => $op, 'value' => $value, 'negate' => $negate, 'raw' => $raw];
    }

    /**
     * 値の形だけを先に確かめる（DB を見ないと分からない「その名前のステータスがあるか」は見ない）。
     */
    private function validate(string $field, string $op, string $value): ?string
    {
        return match ($field) {
            'is' => in_array(mb_strtolower($value), ['open', 'done', 'overdue', 'unassigned', 'watching'], true)
                ? null
                : 'is: に使えるのは open / done / overdue / unassigned / watching です。',
            'priority' => self::priority($value) === null ? "優先度「{$value}」はありません（high / medium / low）。" : null,
            'type' => self::type($value) === null ? "課題タイプ「{$value}」はありません。" : null,
            'due', 'created', 'updated' => ($op === ':' && in_array(mb_strtolower($value), ['none', 'なし'], true))
                || self::date($value) !== null
                    ? null
                    : "日付「{$value}」が読めません（2026-10-31・today・7d・-7d など）。",
            default => null,
        };
    }

    /**
     * @param  Builder<Issue>  $query
     * @param  array{field: string, op: string, value: string, negate: bool, raw: string}  $clause
     */
    private function applyClause(Builder $query, array $clause, User $user): void
    {
        $value = $clause['value'];
        $lower = mb_strtolower($value);

        match ($clause['field']) {
            'status' => $query->whereHas('status', fn (Builder $query) => $query->where('name', $value)),
            'is' => match ($lower) {
                'open' => $query->completed(false),
                'done' => $query->completed(),
                'overdue' => $query->overdue(),
                'unassigned' => $query->whereNull('assignee_id'),
                'watching' => $query->whereHas('watchers', fn (Builder $query) => $query->whereKey($user->id)),
            },
            'assignee' => $this->person($query, 'assignee', $value, $user),
            'reporter' => $this->person($query, 'reporter', $value, $user),
            'priority' => $query->where('priority', self::priority($value)),
            'type' => $query->where('issue_type', self::type($value)),
            'tag' => $query->whereHas('tags', fn (Builder $query) => $query->where('name', $value)),
            'project' => $query->whereHas('project', fn (Builder $query) => $query->where('key', mb_strtoupper($value))),
            'sprint' => match ($lower) {
                'current', 'active', '進行中' => $query->whereHas('sprint', fn (Builder $query) => $query->where('state', SprintState::Active)),
                'none', 'backlog', 'なし' => $query->whereNull('sprint_id'),
                default => $query->whereHas('sprint', fn (Builder $query) => $query->where('name', $value)),
            },
            'due', 'created', 'updated' => $this->compareDate($query, $clause['field'], $clause['op'], $value),
        };
    }

    /**
     * @param  Builder<Issue>  $query
     */
    private function person(Builder $query, string $relation, string $value, User $user): void
    {
        match (mb_strtolower($value)) {
            'me', '自分' => $query->where("{$relation}_id", $user->id),
            'none', 'なし' => $query->whereNull("{$relation}_id"),
            default => $query->whereHas($relation, fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($value, '%_\\').'%')),
        };
    }

    /**
     * @param  Builder<Issue>  $query
     */
    private function compareDate(Builder $query, string $field, string $op, string $value): void
    {
        $column = match ($field) {
            'due' => 'due_date',
            'created' => 'created_at',
            'updated' => 'updated_at',
        };

        if (in_array(mb_strtolower($value), ['none', 'なし'], true)) {
            $query->whereNull($column);

            return;
        }

        $date = self::date($value)->toDateString();

        // 「その日」として比べる。created_at のような日時も日付に丸める
        match ($op) {
            ':' => $query->whereDate($column, '=', $date),
            '<' => $query->whereDate($column, '<', $date),
            '<=' => $query->whereDate($column, '<=', $date),
            '>' => $query->whereDate($column, '>', $date),
            '>=' => $query->whereDate($column, '>=', $date),
        };
    }

    private static function priority(string $value): ?TaskPriority
    {
        $value = mb_strtolower($value);

        return TaskPriority::tryFrom($value)
            ?? collect(TaskPriority::cases())->first(fn (TaskPriority $priority) => $priority->label() === $value);
    }

    private static function type(string $value): ?IssueType
    {
        $value = mb_strtolower($value);

        return IssueType::tryFrom($value)
            ?? collect(IssueType::cases())->first(fn (IssueType $type) => $type->label() === $value);
    }

    /**
     * 日付の書き方: 2026-10-31 / 10/31 / today / tomorrow / yesterday / 7d / -7d / 2w / -1w
     * 7d は「今日から 7 日後」、-7d は「7 日前」。
     */
    private static function date(string $value): ?Carbon
    {
        $value = mb_strtolower($value);

        return match (true) {
            in_array($value, ['today', '今日'], true) => today(),
            in_array($value, ['tomorrow', '明日'], true) => today()->addDay(),
            in_array($value, ['yesterday', '昨日'], true) => today()->subDay(),
            (bool) preg_match('/^(-?\d{1,4})([dw])$/', $value, $m) => today()->addDays((int) $m[1] * ($m[2] === 'w' ? 7 : 1)),
            (bool) preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m) => self::calendarDate((int) $m[1], (int) $m[2], (int) $m[3]),
            (bool) preg_match('/^(\d{1,2})\/(\d{1,2})$/', $value, $m) => self::calendarDate(today()->year, (int) $m[1], (int) $m[2]),
            default => null,
        };
    }

    /**
     * 2026-02-31 のような存在しない日は、繰り上がった日付として通さずに null にする。
     */
    private static function calendarDate(int $year, int $month, int $day): ?Carbon
    {
        return checkdate($month, $day, $year) ? Carbon::create($year, $month, $day) : null;
    }
}
