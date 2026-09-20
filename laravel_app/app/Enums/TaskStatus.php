<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => '未着手',
            self::Doing => '進行中',
            self::Done => '完了',
        };
    }

    /**
     * Tailwind のクラス。バッジの配色をステータスごとに一元管理する。
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Todo => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
            self::Doing => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
            self::Done => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
