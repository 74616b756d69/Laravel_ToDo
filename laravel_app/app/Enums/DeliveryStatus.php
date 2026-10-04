<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '送信待ち',
            self::Succeeded => '成功',
            self::Failed => '失敗',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-200 text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
            self::Succeeded => 'bg-emerald-100 text-emerald-800 ring-emerald-300 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
            self::Failed => 'bg-rose-100 text-rose-800 ring-rose-300 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
        };
    }
}
