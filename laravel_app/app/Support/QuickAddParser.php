<?php

namespace App\Support;

use App\Enums\TaskPriority;
use App\Models\Tag;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 1 行の入力からタスクの属性を取り出す。
 *
 *   「明日 請求書を送る #仕事 !高」
 *     → 期限:明日 / タイトル:請求書を送る / タグ:仕事 / 優先度:高
 *
 * 取り出せなかった部分はすべてタイトルとして残すため、入力が消えることはない。
 */
class QuickAddParser
{
    /** 優先度の記法。表記ゆれをまとめて受け付ける */
    private const PRIORITY_TOKENS = [
        '高' => TaskPriority::High,
        'high' => TaskPriority::High,
        'h' => TaskPriority::High,
        '中' => TaskPriority::Medium,
        'medium' => TaskPriority::Medium,
        'm' => TaskPriority::Medium,
        '低' => TaskPriority::Low,
        'low' => TaskPriority::Low,
        'l' => TaskPriority::Low,
    ];

    /** 曜日の文字 → Carbon の曜日番号 */
    private const WEEKDAYS = ['日' => 0, '月' => 1, '火' => 2, '水' => 3, '木' => 4, '金' => 5, '土' => 6];

    /** @param Collection<int, Tag> $tags ログインユーザーのタグ */
    public function __construct(private readonly Collection $tags) {}

    public function parse(?string $input): ParsedQuickAdd
    {
        // 日本語入力で混ざりがちな全角記号・全角数字を半角に揃えてから解析する
        $text = mb_convert_kana((string) $input, 'as');

        $priority = $this->extractPriority($text);
        [$tagIds, $unknownTags] = $this->extractTags($text);
        $dueDate = $this->extractDueDate($text);

        return new ParsedQuickAdd(
            title: $this->normalizeTitle($text),
            priority: $priority ?? TaskPriority::Medium,
            dueDate: $dueDate,
            tagIds: $tagIds,
            unknownTags: $unknownTags,
        );
    }

    /**
     * `!高` `!high` などを取り出す。複数あれば最後の指定を採用する。
     */
    private function extractPriority(string &$text): ?TaskPriority
    {
        $found = null;

        $text = preg_replace_callback(
            '/!\s*(高|中|低|high|medium|low|h|m|l)(?=\s|$)/iu',
            function (array $matches) use (&$found) {
                $found = self::PRIORITY_TOKENS[mb_strtolower($matches[1])];

                return ' ';
            },
            $text,
        );

        return $found;
    }

    /**
     * `#タグ名` を取り出す。登録済みのタグ名と一致したものだけを採用し、
     * 一致しなかった指定はタイトルに残さずに報告する。
     *
     * @return array{0: array<int, int>, 1: array<int, string>}
     */
    private function extractTags(string &$text): array
    {
        $ids = [];
        $unknown = [];

        $text = preg_replace_callback(
            '/#(\S+)/u',
            function (array $matches) use (&$ids, &$unknown) {
                $name = $matches[1];

                $tag = $this->tags->first(
                    fn (Tag $tag) => mb_strtolower($tag->name) === mb_strtolower($name),
                );

                if ($tag) {
                    $ids[] = $tag->id;
                } else {
                    $unknown[] = $name;
                }

                return ' ';
            },
            $text,
        );

        return [array_values(array_unique($ids)), array_values(array_unique($unknown))];
    }

    /**
     * 日付の表記を 1 つだけ取り出す。具体的な書き方から先に試す。
     */
    private function extractDueDate(string &$text): ?Carbon
    {
        foreach ($this->dueDatePatterns() as $pattern => $resolver) {
            if (preg_match($pattern, $text, $matches)) {
                $text = preg_replace($pattern, ' ', $text, 1);

                return $resolver($matches);
            }
        }

        return null;
    }

    /**
     * 日付の記法と解決方法。並び順がそのまま優先順位になる。
     *
     * @return array<string, callable(array<int, string>): Carbon>
     */
    private function dueDatePatterns(): array
    {
        return [
            // 2026-09-25 / 2026/9/25
            '/(?<![\d\/-])(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})(?![\d\/-])/u' => fn (array $m) => Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay(),

            // 9/25 → 今年の日付。すでに過ぎていれば来年として扱う
            '/(?<![\d\/-])(\d{1,2})[\/-](\d{1,2})(?![\d\/-])/u' => function (array $m) {
                $date = Carbon::create(today()->year, (int) $m[1], (int) $m[2])->startOfDay();

                return $date->isBefore(today()) ? $date->addYear() : $date;
            },

            '/(\d+)日後/u' => fn (array $m) => today()->addDays((int) $m[1]),
            '/(\d+)週間後/u' => fn (array $m) => today()->addWeeks((int) $m[1]),

            // 来週金曜 / 今週金曜（週の始まりは月曜）
            '/来週([月火水木金土日])曜日?/u' => fn (array $m) => $this->weekdayOfWeek($m[1], 1),
            '/今週([月火水木金土日])曜日?/u' => fn (array $m) => $this->weekdayOfWeek($m[1], 0),

            '/今週末/u' => fn () => $this->weekdayOfWeek('土', 0),
            '/来週末/u' => fn () => $this->weekdayOfWeek('土', 1),
            '/今月末/u' => fn () => today()->endOfMonth()->startOfDay(),

            // 単独の曜日は「次に来るその曜日」
            '/([月火水木金土日])曜日?/u' => fn (array $m) => today()->next(self::WEEKDAYS[$m[1]]),

            '/明後日/u' => fn () => today()->addDays(2),
            '/明日/u' => fn () => today()->addDay(),
            '/今日/u' => fn () => today(),
            '/来週/u' => fn () => today()->addWeek(),
            '/来月/u' => fn () => today()->addMonth(),
        ];
    }

    /**
     * 今週（$weekOffset = 0）または来週（1）の指定曜日。
     */
    private function weekdayOfWeek(string $weekday, int $weekOffset): Carbon
    {
        // 月曜を週の始まりとし、日曜は同じ週の末尾に置く
        $offsetFromMonday = (self::WEEKDAYS[$weekday] + 6) % 7;

        return today()->startOfWeek()->addWeeks($weekOffset)->addDays($offsetFromMonday);
    }

    /**
     * 記法を取り除いた残りをタイトルにする。
     */
    private function normalizeTitle(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
