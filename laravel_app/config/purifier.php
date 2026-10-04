<?php

/**
 * リッチテキスト用の HTMLPurifier 設定。
 * エディタ（Tiptap）が生成しうるタグだけを許可し、それ以外は保存前に除去する。
 */
return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p,br,span',
            'CSS.AllowedProperties' => '',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty' => true,
        ],

        'task' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,em,s,code,pre,h2,h3,blockquote,hr,'
                .'a[href|title|target|rel],div,span[data-type|data-id|data-label],label,'
                .'ul[data-type],ol,li[data-type|data-checked],'
                .'input[type|checked|disabled],'
                .'img[src|alt]',
            'HTML.TargetBlank' => true,
            'HTML.Nofollow' => true,
            // href は http(s) とメールのみ許可（javascript: スキームを弾く）
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
            // 画像は自分のサーバー（添付ファイル）のものだけ。外部の画像を許すと、
            // 開いた人の IP や閲覧時刻を第三者に送る「トラッキング画像」を埋め込める
            'URI.DisableExternalResources' => true,
            'CSS.AllowedProperties' => '',
            // チェックリストは空の <span> を含むため、空要素の自動削除は行わない
            'AutoFormat.RemoveEmpty' => false,
        ],

        /**
         * HTML 4.01 に存在しない Tiptap 固有のマークアップを定義に追加する。
         * これが無いとチェックリストの構造ごと除去されてしまう。
         */
        'custom_definition' => [
            'id' => 'tiptap-task-content',
            'rev' => 4,
            'debug' => false,
            'elements' => [
                ['label', 'Inline', 'Inline', 'Common'],
                // type は checkbox だけ。自由にすると、コメント本文に
                // 偽のパスワード欄のような紛らわしい部品を描けてしまう
                ['input', 'Inline', 'Empty', 'Common', [
                    'type' => 'Enum#checkbox',
                    'checked' => 'Text',
                    'disabled' => 'Text',
                ]],
            ],
            'attributes' => [
                ['ul', 'data-type', 'Text'],
                ['li', 'data-type', 'Text'],
                ['li', 'data-checked', 'Text'],
                // @メンション。中身の正しさは App\Support\Mentions::normalize() が保存前に確かめる
                ['span', 'data-type', 'Enum#mention'],
                ['span', 'data-id', 'Text'],
                ['span', 'data-label', 'Text'],
            ],
        ],
    ],
];
