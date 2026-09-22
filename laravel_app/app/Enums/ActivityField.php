<?php

namespace App\Enums;

/**
 * 履歴に残す変更の種類。
 *
 * 値は DB に入るので、増やすのはよいが既存の値は変えないこと
 * （履歴は不変なので、過去の行が読めなくなる）。
 */
enum ActivityField: string
{
    case Created = 'created';
    case Status = 'status';
    case Assignee = 'assignee';
    case Priority = 'priority';
    case Sprint = 'sprint';
    case StoryPoints = 'story_points';

    public function label(): string
    {
        return match ($this) {
            self::Created => '作成',
            self::Status => 'ステータス',
            self::Assignee => '担当者',
            self::Priority => '優先度',
            self::Sprint => 'スプリント',
            self::StoryPoints => 'ストーリーポイント',
        };
    }

    /**
     * 履歴 1 行の文。値が無い側は「未設定」と読ませる。
     */
    public function describe(?string $old, ?string $new): string
    {
        if ($this === self::Created) {
            return '課題を作成しました。';
        }

        return match (true) {
            $old === null => "{$this->label()}を「{$new}」に設定しました。",
            $new === null => "{$this->label()}「{$old}」を解除しました。",
            default => "{$this->label()}を「{$old}」から「{$new}」に変更しました。",
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Created => 'bg-slate-200 text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
            self::Status => 'bg-sky-100 text-sky-800 ring-sky-300 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
            self::Assignee => 'bg-violet-100 text-violet-800 ring-violet-300 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30',
            self::Priority => 'bg-amber-100 text-amber-800 ring-amber-300 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
            self::Sprint => 'bg-emerald-100 text-emerald-800 ring-emerald-300 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
            self::StoryPoints => 'bg-slate-200 text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
        };
    }

    /**
     * 自動記録の対象になるカラムと、その種類の対応。
     *
     * ここに載っているカラムが変わったときだけ履歴を残す。
     *
     * @return array<string, self>
     */
    public static function trackedColumns(): array
    {
        return [
            'status_id' => self::Status,
            'assignee_id' => self::Assignee,
            'priority' => self::Priority,
            'sprint_id' => self::Sprint,
            'story_points' => self::StoryPoints,
        ];
    }
}
