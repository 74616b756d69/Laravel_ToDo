<?php

namespace App\Support;

/**
 * 作業時間の書き方と分の相互変換。
 *
 * 受け付ける書き方:
 *   1h30m / 1h 30m / 90m / 1.5h / 2h / 1d（= 8 時間）/ 1時間30分 / 45分 / 2（単位なしは時間）
 *
 * 表示は「1時間30分」「45分」「2時間」。日（d）は入力の近道としてだけ受け、表示には使わない
 * （1 日が何時間かは人によって違うので、表示で換算すると読み違える）。
 */
class Duration
{
    /** 1d を何分とみなすか */
    public const MINUTES_PER_DAY = 8 * 60;

    /** 1 件あたりの上限（分）。桁を打ち間違えた「100h」などを止める */
    public const MAX_MINUTES = 24 * 60;

    /**
     * 書かれた時間を分にする。読めなければ null。0 分も null（記録する意味がない）。
     */
    public static function parse(?string $input): ?int
    {
        $text = mb_strtolower(trim((string) $input));
        $text = str_replace(['時間', '分', '日', ' ', '　'], ['h', 'm', 'd', '', ''], $text);

        if ($text === '') {
            return null;
        }

        // 単位なしの数字は時間として読む
        if (preg_match('/^\d+(\.\d+)?$/', $text)) {
            $text .= 'h';
        }

        if (! preg_match('/^(?:(\d+(?:\.\d+)?)d)?(?:(\d+(?:\.\d+)?)h)?(?:(\d+)m)?$/', $text, $m)) {
            return null;
        }

        $minutes = (int) round(
            (float) ($m[1] ?? 0) * self::MINUTES_PER_DAY
            + (float) ($m[2] ?? 0) * 60
            + (int) ($m[3] ?? 0),
        );

        return $minutes > 0 ? $minutes : null;
    }

    public static function format(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => "{$rest}分",
            $rest === 0 => "{$hours}時間",
            default => "{$hours}時間{$rest}分",
        };
    }

    /**
     * 入力欄に戻すときの書き方（1h30m）。表示用の日本語より打ち直しやすい。
     */
    public static function toInput(?int $minutes): string
    {
        if ($minutes === null) {
            return '';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return ($hours > 0 ? "{$hours}h" : '').($rest > 0 ? "{$rest}m" : '');
    }
}
