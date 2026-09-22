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

    /**
     * 種別を示すアイコン名。色だけでなく形でも見分けられるようにする。
     */
    public function icon(): string
    {
        return match ($this) {
            self::Epic => 'epic',
            self::Story => 'story',
            self::Task => 'task',
            self::Bug => 'bug',
            self::Subtask => 'subtask',
        };
    }

    /**
     * アイコン単体に載せる文字色。バッジの背景を使わない場所で用いる。
     */
    public function iconClasses(): string
    {
        return match ($this) {
            self::Epic => 'text-violet-600 dark:text-violet-400',
            self::Story => 'text-emerald-600 dark:text-emerald-400',
            self::Task => 'text-sky-600 dark:text-sky-400',
            self::Bug => 'text-rose-600 dark:text-rose-400',
            self::Subtask => 'text-slate-400 dark:text-slate-500',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Epic => 'bg-violet-100 text-violet-800 ring-violet-300 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30',
            self::Story => 'bg-emerald-100 text-emerald-800 ring-emerald-300 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
            self::Task => 'bg-sky-100 text-sky-800 ring-sky-300 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
            self::Bug => 'bg-rose-100 text-rose-800 ring-rose-300 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
            self::Subtask => 'bg-slate-200 text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
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
