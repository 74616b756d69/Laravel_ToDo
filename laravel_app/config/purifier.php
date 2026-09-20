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
                .'a[href|title|target|rel],div,span,label,'
                .'ul[data-type],ol,li[data-type|data-checked],'
                .'input[type|checked|disabled]',
            'HTML.TargetBlank' => true,
            'HTML.Nofollow' => true,
            // href は http(s) とメールのみ許可（javascript: スキームを弾く）
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
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
            'rev' => 1,
            'debug' => false,
            'elements' => [
                ['label', 'Inline', 'Inline', 'Common'],
                ['input', 'Inline', 'Empty', 'Common', [
                    'type' => 'Text',
                    'checked' => 'Text',
                    'disabled' => 'Text',
                ]],
            ],
            'attributes' => [
                ['ul', 'data-type', 'Text'],
                ['li', 'data-type', 'Text'],
                ['li', 'data-checked', 'Text'],
            ],
        ],
    ],
];
