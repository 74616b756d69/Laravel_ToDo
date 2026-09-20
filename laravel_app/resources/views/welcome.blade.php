@extends('layouts.app')

@section('title', 'ホーム')

@section('content')
    <section class="animate-rise py-10 text-center sm:py-16">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 ring-1 ring-brand-200 ring-inset dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-500/30">
            <x-icon name="sparkles" class="size-3.5" /> Laravel 製タスク管理アプリ
        </span>

        <h1 class="mt-5 text-4xl font-bold tracking-tight text-balance sm:text-5xl">
            やることを、<span class="bg-gradient-to-r from-brand-500 to-brand-700 bg-clip-text text-transparent">迷わず片づける。</span>
        </h1>

        <p class="mx-auto mt-4 max-w-xl text-pretty text-slate-600 dark:text-slate-300">
            ステータス・優先度・期限で整理し、検索と絞り込みで必要なタスクだけに集中できる
            シンプルな ToDo アプリです。
        </p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @auth
                <a href="{{ route('tasks.index') }}"
                   class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">タスク一覧へ</a>
            @else
                <a href="{{ route('register') }}"
                   class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">無料ではじめる</a>
                <a href="{{ route('login') }}"
                   class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold transition hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-white/5">ログイン</a>
            @endauth
        </div>

        <div class="mt-14 grid gap-4 text-left sm:grid-cols-3">
            @foreach ([
                ['flag', '優先度とステータス', '未着手・進行中・完了と3段階の優先度で、今やるべきことが一目でわかります。'],
                ['calendar', '期限アラート', '期限が近いタスクや過ぎたタスクを色で強調し、抜け漏れを防ぎます。'],
                ['search', '検索と絞り込み', 'キーワード・ステータス・優先度を組み合わせて必要なタスクだけを表示します。'],
            ] as [$icon, $title, $body])
                <div class="card p-5">
                    <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                        <x-icon :name="$icon" class="size-5" />
                    </span>
                    <h2 class="mt-3 font-semibold">{{ $title }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endsection
