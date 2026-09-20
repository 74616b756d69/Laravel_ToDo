<?php

namespace App\Enums;

/**
 * プロジェクト内での役割。権限判定の唯一の根拠にする。
 */
enum ProjectRole: string
{
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => '管理者',
            self::Member => 'メンバー',
            self::Viewer => '閲覧者',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'プロジェクト設定とメンバーを変更できます。',
            self::Member => '課題の作成と編集ができます。',
            self::Viewer => '閲覧のみできます。',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Admin => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30',
            self::Member => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
            self::Viewer => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
