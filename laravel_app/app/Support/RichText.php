<?php

namespace App\Support;

use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * エディタから受け取った HTML の無害化と、検索・要約に使う平文への変換をまとめる。
 */
class RichText
{
    /**
     * 許可タグ以外を取り除いた HTML を返す。中身が空なら null。
     */
    public static function sanitize(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $clean = trim(Purifier::clean($html, 'task'));

        // タグだけ残った実質空の入力は保存しない
        return self::toPlainText($clean) === '' && ! str_contains($clean, '<hr')
            ? null
            : $clean;
    }

    /**
     * タグを除いた本文。全文検索と一覧の抜粋に使う。
     */
    public static function toPlainText(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        // ブロック要素の切れ目が単語結合しないよう空白に置き換えてから除去する
        $text = preg_replace('/<(br|\/p|\/li|\/h2|\/h3|\/blockquote)[^>]*>/i', ' ', $html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8')));
    }

    /**
     * 閲覧画面用の HTML。
     * チェックリストのチェックボックスは、押しても保存されないので無効化しておく。
     */
    public static function forDisplay(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        return str_replace('<input type="checkbox"', '<input disabled type="checkbox"', $html);
    }

    /**
     * 一覧に出す抜粋。
     */
    public static function excerpt(?string $html, int $limit = 120): string
    {
        return Str::limit(self::toPlainText($html), $limit);
    }
}
