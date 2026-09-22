<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => '低',
            self::Medium => '中',
            self::High => '高',
        };
    }

    /**
     * 並び替え用の重み。SQL 側で CASE 式に展開する。
     */
    public function weight(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }

    /**
     * 優先度の記号。高は上向き、中は横棒、低は下向き。
     * 色だけに頼らず、形でも高低が分かるようにする。
     */
    public function icon(): string
    {
        return match ($this) {
            self::High => 'priority-high',
            self::Medium => 'priority-medium',
            self::Low => 'priority-low',
        };
    }

    public function iconClasses(): string
    {
        return match ($this) {
            self::High => 'text-rose-500',
            self::Medium => 'text-amber-500',
            self::Low => 'text-slate-400',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-200 text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
            self::Medium => 'bg-amber-100 text-amber-800 ring-amber-300 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
            self::High => 'bg-rose-100 text-rose-800 ring-rose-300 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-400',
            self::Medium => 'bg-amber-500',
            self::High => 'bg-rose-500',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $priority) => [$priority->value => $priority->label()])
            ->all();
    }
}
