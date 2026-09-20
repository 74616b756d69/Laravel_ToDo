<?php

/**
 * デモ（お試し）アカウントの設定。
 *
 * ポートフォリオとして公開したときに、閲覧者がアカウントを作らずに
 * 中身を確認できるようにするための仕組み。
 * 本番で無効化できるよう、必ず環境変数で切り替えられるようにしている。
 */
return [
    'enabled' => (bool) env('DEMO_LOGIN_ENABLED', true),

    'email' => env('DEMO_LOGIN_EMAIL', 'demo@example.com'),

    'password' => env('DEMO_LOGIN_PASSWORD', 'password123'),
];
