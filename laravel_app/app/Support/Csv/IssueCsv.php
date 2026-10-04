<?php

namespace App\Support\Csv;

use App\Models\Issue;
use App\Support\Duration;

/**
 * 課題の CSV の列。エクスポートとインポートで同じ見出しを使う。
 *
 * 書き出したファイルをそのまま（見出しを変えずに）読み込めるようにするため。
 * インポートが読むのは IMPORTABLE の列だけで、それ以外（キー・日時など）は無視する。
 */
class IssueCsv
{
    /** 書き出す列。順番もこのとおり */
    public const HEADERS = [
        'キー', 'タイトル', '説明', 'タイプ', 'ステータス', '優先度', '担当者', '担当者メール', '起票者',
        'スプリント', 'ストーリーポイント', '見積もり（分）', '実績（分）', '期限', 'タグ', '作成日時', '更新日時', '完了日時',
    ];

    /** 読み込む列 */
    public const IMPORTABLE = [
        'タイトル', '説明', 'タイプ', 'ステータス', '優先度', '担当者メール', 'ストーリーポイント', '見積もり（分）', '期限', 'タグ',
    ];

    /** タグを 1 つの欄に並べるときの区切り */
    public const TAG_SEPARATOR = ',';

    /**
     * 課題 1 件を 1 行にする。呼ぶ側で必要な関連（project, status, assignee, reporter, sprint, tags）と
     * worklogs_sum_minutes を読んでおくこと。
     *
     * @return list<string>
     */
    public static function row(Issue $issue): array
    {
        return array_map(self::cell(...), [
            $issue->key(),
            $issue->title,
            $issue->content_text ?? '',
            $issue->issue_type->label(),
            $issue->status->name,
            $issue->priority->label(),
            $issue->assignee?->name ?? '',
            $issue->assignee?->email ?? '',
            $issue->reporter?->name ?? '',
            $issue->sprint?->name ?? '',
            (string) ($issue->story_points ?? ''),
            (string) ($issue->original_estimate_minutes ?? ''),
            (string) ((int) $issue->getAttribute('worklogs_sum_minutes')),
            $issue->due_date?->format('Y-m-d') ?? '',
            $issue->tags->pluck('name')->implode(self::TAG_SEPARATOR),
            $issue->created_at?->format('Y-m-d H:i:s') ?? '',
            $issue->updated_at?->format('Y-m-d H:i:s') ?? '',
            $issue->completed_at?->format('Y-m-d H:i:s') ?? '',
        ]);
    }

    /**
     * 表計算ソフトで開いたときに数式として動かないよう、危ない先頭文字を逃がす（CSV インジェクション対策）。
     *
     * 課題のタイトルに「=HYPERLINK(…)」と書かれていると、開いた人の Excel で実行されてしまう。
     */
    public static function cell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * 読み込み時に、書き出しで付けた ' を外す。
     */
    public static function uncell(string $value): string
    {
        return preg_match('/^\'[=+\-@\t\r]/', $value) ? substr($value, 1) : $value;
    }

    /**
     * 「見積もり（分）」は分の整数。書き出しと同じ形だけでなく、「3h」のような書き方も受ける。
     */
    public static function minutes(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return ctype_digit($value) ? (int) $value : Duration::parse($value);
    }
}
