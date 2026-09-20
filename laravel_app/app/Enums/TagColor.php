<?php

namespace App\Enums;

/**
 * タグの配色。任意の CSS を保存させず、決め打ちの候補から選ばせる。
 */
enum TagColor: string
{
    case Slate = 'slate';
    case Rose = 'rose';
    case Amber = 'amber';
    case Emerald = 'emerald';
    case Sky = 'sky';
    case Violet = 'violet';

    public function label(): string
    {
        return match ($this) {
            self::Slate => 'グレー',
            self::Rose => 'レッド',
            self::Amber => 'オレンジ',
            self::Emerald => 'グリーン',
            self::Sky => 'ブルー',
            self::Violet => 'パープル',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Slate => 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
            self::Rose => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
            self::Amber => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
            self::Emerald => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
            self::Sky => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
            self::Violet => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30',
        };
    }

    public function swatchClasses(): string
    {
        return match ($this) {
            self::Slate => 'bg-slate-400',
            self::Rose => 'bg-rose-500',
            self::Amber => 'bg-amber-500',
            self::Emerald => 'bg-emerald-500',
            self::Sky => 'bg-sky-500',
            self::Violet => 'bg-violet-500',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $color) => [$color->value => $color->label()])
            ->all();
    }
}
