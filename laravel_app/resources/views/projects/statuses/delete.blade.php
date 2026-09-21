@extends('layouts.app')

@section('title', $status->name.' を削除')

@section('content')
    <x-page-heading title="ステータスを削除"
                    :back="route('projects.edit', $project)" back-label="プロジェクト設定に戻る" />

    <div class="card mb-6 p-5 sm:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <x-badge :classes="$status->badgeClasses()" :dot="$status->dotClasses()">
                {{ $status->category->label() }}
            </x-badge>
            <h2 class="text-lg font-bold">{{ $status->name }}</h2>
        </div>

        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
            このステータスを出入り口にしている遷移も一緒に消えます。
            ボードのレーンも 1 本減ります。
        </p>
    </div>

    <form action="{{ route('projects.statuses.destroy', [$project, $status]) }}" method="POST"
          class="card p-5 sm:p-6">
        @csrf
        @method('DELETE')

        @if ($issues->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">
                このステータスの課題はありません。そのまま削除できます。
            </p>
        @else
            <h3 class="text-sm font-semibold">残っている課題の移送先</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $issues->count() }} 件の課題を、選んだステータスへ移してから削除します。
            </p>

            <div class="mt-4 space-y-2">
                @foreach ($destinations as $destination)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 transition has-checked:border-brand-500 has-checked:bg-brand-50 dark:border-slate-700 dark:has-checked:bg-brand-500/10">
                        <input type="radio" name="destination" value="{{ $destination->id }}"
                               class="mt-0.5 size-4 text-brand-600" @checked($loop->first)>
                        <span>
                            <span class="block text-sm font-medium">{{ $destination->name }} へ移す</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                {{ $destination->category->label() }}として扱われます
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>

            <x-input-error :messages="$errors->get('destination')" />

            <details class="mt-4">
                <summary class="cursor-pointer list-none text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                    移送される {{ $issues->count() }} 件を確認する
                </summary>
                <ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-white/5 dark:border-slate-700">
                    @foreach ($issues as $issue)
                        <li class="flex flex-wrap items-center gap-3 px-3 py-2">
                            <a href="{{ route('tasks.show', $issue) }}"
                               class="font-mono text-[11px] tracking-wider text-slate-400 hover:text-brand-700 dark:text-slate-500 dark:hover:text-brand-300">
                                {{ $issue->key() }}
                            </a>
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $issue->title }}</span>
                            @if ($issue->assignee)
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $issue->assignee->name }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        <x-input-error :messages="$errors->get('workflow')" />

        <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-5 dark:border-white/5">
            <a href="{{ route('projects.edit', $project) }}"
               class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5">
                キャンセル
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700">
                <x-icon name="trash" class="size-4" /> 削除する
            </button>
        </div>
    </form>
@endsection
