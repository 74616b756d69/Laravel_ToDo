<?php

namespace App\Enums;

/**
 * 課題の種別。階層の作り方（親を持てるか）もここで決める。
 */
enum IssueType: string
{
    case Epic = 'epic';
    case Story = 'story';
    case Task = 'task';
    case Bug = 'bug';
    case Subtask = 'subtask';

    public function label(): string
    {
        return match ($this) {
            self::Epic => 'エピック',
            self::Story => 'ストーリー',
            self::Task => 'タスク',
            self::Bug => 'バグ',
            self::Subtask => 'サブタスク',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Epic => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30',
            self::Story => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
            self::Task => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
            self::Bug => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
            self::Subtask => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
        };
    }

    /**
     * サブタスクとして作られた課題か。
     *
     * 「親を持つか」は Issue::isChild() で見ること。既存の Bug や Story も
     * 親の下に入れられるので、種別と親子関係は一致しない。
     */
    public function isSubtask(): bool
    {
        return $this === self::Subtask;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
