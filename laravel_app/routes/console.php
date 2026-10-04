<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 古い記録の掃除（Webhook の配信履歴など、MassPrunable を使うモデル）
Schedule::command('model:prune')->daily();
