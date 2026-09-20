<?php

namespace App\Support;

use App\Models\Issue;
use Illuminate\Support\Collection;

/**
 * 課題詳細の下部に並べる、コメントと履歴の時系列。
 *
 * 型の違うものを 1 本に混ぜるので、表示側が分岐できるよう kind を添える。
 */
class IssueTimeline
{
    /** 選べるタブ */
    public const TABS = ['all', 'comments', 'history'];

    public const DEFAULT_TAB = 'all';

    /**
     * クエリ文字列を、扱えるタブ名に丸める。
     */
    public static function tab(?string $requested): string
    {
        return in_array($requested, self::TABS, strict: true) ? $requested : self::DEFAULT_TAB;
    }

    /**
     * 呼ぶ前に comments.user と activities.user を eager load しておくこと。
     *
     * @return Collection<int, array{kind: string, at: \Illuminate\Support\Carbon, item: mixed}>
     */
    public static function build(Issue $issue, string $tab): Collection
    {
        // toBase() で素の Collection に落とす。
        // Eloquent Collection のまま配列を混ぜると、並べ替えがモデルを期待して壊れる
        $comments = $tab === 'history'
            ? collect()
            : $issue->comments->toBase()->map(fn ($comment) => [
                'kind' => 'comment', 'at' => $comment->created_at, 'item' => $comment,
            ]);

        $activities = $tab === 'comments'
            ? collect()
            : $issue->activities->toBase()->map(fn ($activity) => [
                'kind' => 'activity', 'at' => $activity->created_at, 'item' => $activity,
            ]);

        // 同時刻なら履歴を先に出す（「変更した、だからコメントした」の順に読める）
        return $comments->merge($activities)
            ->sortBy([['at', 'asc'], ['kind', 'asc']])
            ->values();
    }
}
