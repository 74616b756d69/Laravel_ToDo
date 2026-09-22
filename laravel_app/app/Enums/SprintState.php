<?php

namespace App\Enums;

/**
 * スプリントの状態。
 *
 * future → active → closed の一方通行。戻すことはできない。
 * 同時に active にできるのはプロジェクトごとに 1 つだけ。
 */
enum SprintState: string
{
    case Future = 'future';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Future => '未開始',
            self::Active => '進行中',
            self::Closed => '完了',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Future => 'bg-slate-200 text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
            self::Active => 'bg-emerald-100 text-emerald-800 ring-emerald-300 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
            self::Closed => 'bg-slate-200 text-slate-500 ring-slate-300 dark:bg-slate-800 dark:text-slate-500 dark:ring-slate-700',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::Future => 'bg-slate-400',
            self::Active => 'bg-emerald-500',
            self::Closed => 'bg-slate-300',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $state) => [$state->value => $state->label()])
            ->all();
    }
}
