<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 日付の表示（isoFormat / diffForHumans）を日本語にする
        Carbon::setLocale('ja');

        // 本番以外では N+1 や未定義属性へのアクセスを例外として検出する
        Model::shouldBeStrict(! $this->app->isProduction());

        Vite::prefetch(concurrency: 3);
    }
}
