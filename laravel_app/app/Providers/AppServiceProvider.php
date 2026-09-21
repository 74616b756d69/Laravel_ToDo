<?php

namespace App\Providers;

use App\Support\ProjectContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
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

        // ヘッダーは全画面に出るので、切り替え UI の材料はここでまとめて渡す
        View::composer('partials.header', function (\Illuminate\View\View $view) {
            if (! Auth::check()) {
                return;
            }

            $context = $this->app->make(ProjectContext::class);

            $view->with([
                'currentProject' => $context->current(Auth::user()),
                'availableProjects' => $context->available(Auth::user()),
            ]);
        });

        Vite::prefetch(concurrency: 3);
    }
}
