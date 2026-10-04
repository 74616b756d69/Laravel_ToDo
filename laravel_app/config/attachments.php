<?php

/**
 * 課題の添付ファイル。
 *
 * 保存先は ATTACHMENTS_DISK で切り替える（既定はローカルの非公開領域、本番は s3 を想定）。
 * どちらでも、ダウンロードは必ずアプリを通して権限を確かめてから返す。
 */
return [
    'disk' => env('ATTACHMENTS_DISK', 'attachments'),

    // 1 ファイルの上限（KB）。PHP の upload_max_filesize / post_max_size もこれ以上にしておくこと
    'max_size' => (int) env('ATTACHMENTS_MAX_KB', 10240),

    // 1 課題あたりの上限件数
    'max_per_issue' => (int) env('ATTACHMENTS_MAX_PER_ISSUE', 50),

    /*
     * 受け付ける拡張子。判定は拡張子ではなく中身（MIME）で行う（バリデーションの mimes）。
     *
     * SVG と HTML は入れない。同じオリジンからそのまま開かれると、
     * 中のスクリプトがアプリの Cookie で動いてしまう（保存型 XSS）。
     */
    'extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'txt', 'csv', 'md', 'json', 'log',
        'zip', 'docx', 'xlsx', 'pptx',
    ],

    // ブラウザの中で開いてよい種類。それ以外は必ずダウンロードさせる
    'inline_mime_types' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf',
    ],
];
