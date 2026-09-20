@extends('layouts.app')

@section('title', 'ボード')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">ボード</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                カードをドラッグしてステータスと並び順を変更できます。
            </p>
        </div>
        <a href="{{ route('tasks.create') }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
            <x-icon name="plus" class="size-4" /> 新規タスク
        </a>
    </div>

    <div data-board class="grid gap-3 md:grid-cols-3">
        @foreach ($lanes as $key => $lane)
            <section class="card flex flex-col overflow-hidden">
                <header class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 dark:border-white/5">
                    <x-badge :classes="$lane['status']->badgeClasses()">{{ $lane['status']->label() }}</x-badge>
                    <span class="ml-auto text-xs font-medium text-slate-500 tabular-nums dark:text-slate-400"
                          data-lane-count="{{ $key }}">{{ $lane['tasks']->count() }}</span>
                </header>

                {{-- data-lane の値がドロップ先のステータスになる --}}
                <ul data-lane="{{ $key }}" class="flex min-h-32 flex-1 flex-col gap-2 p-3">
                    @foreach ($lane['tasks'] as $task)
                        <li data-task-id="{{ $task->id }}" data-move-url="{{ route('board.move', $task) }}"
                            class="cursor-grab rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800">
                            <a href="{{ route('tasks.show', $task) }}"
                               class="block text-sm font-medium hover:text-brand-700 dark:hover:text-brand-300">
                                {{ $task->title }}
                            </a>

                            @if ($task->subtasks_count > 0)
                                <div class="mt-2">
                                    <x-progress-bar :done="$task->done_subtasks_count" :total="$task->subtasks_count" compact />
                                </div>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                <x-badge :classes="$task->priority->badgeClasses()" :dot="$task->priority->dotClasses()">
                                    {{ $task->priority->label() }}
                                </x-badge>

                                @foreach ($task->tags as $tag)
                                    <x-badge :classes="$tag->color->badgeClasses()">{{ $tag->name }}</x-badge>
                                @endforeach

                                @if ($task->due_date)
                                    <x-badge :classes="$task->isOverdue()
                                            ? 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30'
                                            : 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'">
                                        <x-icon name="calendar" class="size-3.5" /> {{ $task->due_date->format('n/j') }}
                                    </x-badge>
                                @endif
                            </div>
                        </li>
                    @endforeach

                    <li data-lane-empty
                        class="{{ $lane['tasks']->isEmpty() ? '' : 'hidden' }} rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400 dark:border-slate-700 dark:text-slate-500">
                        ここにドロップ
                    </li>
                </ul>
            </section>
        @endforeach
    </div>

    <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
        ※ ドラッグ＆ドロップには JavaScript が必要です。無効な場合は
        <a href="{{ route('tasks.index') }}" class="underline">タスク一覧</a> の編集画面から変更できます。
    </p>
@endsection
