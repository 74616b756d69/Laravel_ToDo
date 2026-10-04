<?php

/**
 * Webhook の送信。
 *
 * 送り先は利用者が決める URL なので、そのままだとサーバーを踏み台にして
 * 社内ネットワークやクラウドのメタデータ（169.254.169.254）を叩けてしまう（SSRF）。
 * 既定ではグローバルなアドレスにしか送らない。
 */
return [
    // http も許すか。本番では https だけにしておく
    'allow_http' => (bool) env('WEBHOOKS_ALLOW_HTTP', false),

    // プライベート / ループバック / リンクローカルのアドレスへも送るか。
    // 手元で受け口を立てて試すとき（localhost など）だけ true にする
    'allow_private_hosts' => (bool) env('WEBHOOKS_ALLOW_PRIVATE_HOSTS', false),

    // 1 回の送信を待つ秒数。相手が遅くてもワーカーを長く握らない
    'timeout' => (int) env('WEBHOOKS_TIMEOUT', 5),

    // 失敗したときの再試行の間隔（秒）。この数 + 1 回まで送る
    'backoff' => [10, 60, 300, 900, 3600],
];
