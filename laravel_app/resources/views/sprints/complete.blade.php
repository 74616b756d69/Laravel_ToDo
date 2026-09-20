@extends('layouts.app')

@section('title', $sprint->name.' を完了')

@section('content')
    <x-page-heading title="スプリントを完了" :back="route('backlog')" back-label="バックログに戻る" />

    <div class="card mb-6 p-5 sm:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <x-badge :classes="$sprint->state->badgeClasses()" :dot="$sprint->state->dotClasses()">
                {{ $sprint->state->label() }}
            </x-badge>
            <h2 class="text-lg font-bold">{{ $sprint->name }}</h2>
            @if ($sprint->period())
                <span class="text-sm text-slate-500 dark:text-slate-400">{{ $sprint->period() }}</span>
            @endif
        </div>

        @if ($sprint->goal)
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $sprint->goal }}</p>
        @endif

        <div class="mt-4 flex flex-wrap items-baseline gap-x-6 gap-y-1 border-t border-slate-100 pt-4 text-sm dark:border-white/5">
            <p>
                <span class="text-slate-500 dark:text-slate-400">完了した課題</span>
                <span class="ml-1.5 text-lg font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $completed }}</span> 件
            </p>
            <p>
                <span class="text-slate-500 dark:text-slate-400">残っている課題</span>
                <span class="ml-1.5 text-lg font-bold tabular-nums">{{ $incomplete->count() }}</span> 件
            </p>
        </div>
    </div>

    <form action="{{ route('sprints.complete.store', $sprint) }}" method="POST" class="card p-5 sm:p-6">
        @csrf

        <h3 class="text-sm font-semibold">残った課題の移送先</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            完了した課題はこのスプリントに残して実績にします。
            未完了の {{ $incomplete->count() }} 件だけを移します。
        </p>

        <div class="mt-4 space-y-2">
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 transition has-checked:border-brand-500 has-checked:bg-brand-50 dark:border-slate-700 dark:has-checked:bg-brand-500/10">
                <input type="radio" name="destination" value="" class="mt-0.5 size-4 text-brand-600" checked>
                <span>
                    <span class="block text-sm font-medium">バックログへ戻す</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                        次にやることは、あとであらためて決める
                    </span>
                </span>
            </label>

            @foreach ($destinations as $destination)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 transition has-checked:border-brand-500 has-checked:bg-brand-50 dark:border-slate-700 dark:has-checked:bg-brand-500/10">
                    <input type="radio" name="destination" value="{{ $destination->id }}"
                           class="mt-0.5 size-4 text-brand-600">
                    <span>
                        <span class="block text-sm font-medium">{{ $destination->name }} へ送る</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                            {{ $destination->period() ?? '期間は未設定' }}
                        </span>
                    </span>
                </label>
            @endforeach
        </div>

        <x-input-error :messages="$errors->get('destination')" />
        <x-input-error :messages="$errors->get('sprint')" />

        {{-- 何が動くのかを見せてから実行させる --}}
        @if ($incomplete->isNotEmpty())
            <details class="mt-4">
                <summary class="cursor-pointer list-none text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                    移送される {{ $incomplete->count() }} 件を確認する
                </summary>
                <ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-white/5 dark:border-slate-700">
                    @foreach ($incomplete as $issue)
                        <li class="flex flex-wrap items-center gap-3 px-3 py-2">
                            <span class="font-mono text-[11px] tracking-wider text-slate-400 dark:text-slate-500">
                                {{ $issue->key() }}
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $issue->title }}</span>
                            <x-badge :classes="$issue->status->badgeClasses()">{{ $issue->status->name }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-5 dark:border-white/5">
            <a href="{{ route('backlog') }}"
               class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5">
                キャンセル
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                <x-icon name="check" class="size-4" /> スプリントを完了する
            </button>
        </div>
    </form>
@endsection
