<?php

namespace App\Enums;

/**
 * ステータスの意味づけ。
 *
 * ステータス名はプロジェクトごとに自由に決められるが、
 * 「完了かどうか」「着手済みかどうか」はアプリ側が判断する必要がある
 * （期限切れの判定、完了率、分析グラフなど）。その拠り所がこのカテゴリ。
 */
enum StatusCategory: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => '未着手',
            self::InProgress => '進行中',
            self::Done => '完了',
        };
    }

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    /**
     * Tailwind のクラス。レーンやバッジの配色をカテゴリごとに一元管理する。
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Todo => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
            self::InProgress => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
            self::Done => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::Todo => 'bg-slate-400',
            self::InProgress => 'bg-sky-500',
            self::Done => 'bg-emerald-500',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category) => [$category->value => $category->label()])
            ->all();
    }
}
