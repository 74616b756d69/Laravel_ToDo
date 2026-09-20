@extends('layouts.app')

@section('title', 'ボード')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">ボード</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold tracking-wider text-slate-600 dark:bg-white/5 dark:text-slate-300">
                    {{ $project->key }}
                </span>
                カードをドラッグしてステータスと並び順を変更できます。
            </p>
        </div>
        {{-- 各レーンから追加できるので、ここは詳細入力への導線だけにする --}}
        <a href="{{ route('tasks.create') }}"
           class="text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            詳しく入力して作成 →
        </a>
    </div>

    {{--
        3 レーンの高さを揃え、はみ出した分はレーンの中だけでスクロールさせる。
        画面の高さから、ヘッダーと見出しのぶんを引いた高さを割り当てている。
    --}}
    {{--
        レーンはプロジェクトの statuses から作るので本数が可変。
        許可されていない遷移は data-allowed-transitions を見て画面側で止める。
    --}}
    <div data-board
         data-allowed-transitions="{{ json_encode($allowedTransitions) }}"
         style="--lane-count: {{ $lanes->count() }}"
         class="grid gap-3 md:h-[calc(100vh-15rem)] md:min-h-96 md:grid-cols-[repeat(var(--lane-count),minmax(0,1fr))]">
        @foreach ($lanes as $lane)
            @php($key = $lane['status']->id)
            <section class="card flex max-h-[70vh] min-h-0 flex-col overflow-hidden md:max-h-none">
                <header class="flex shrink-0 items-center gap-2 border-b border-slate-100 px-4 py-3 dark:border-white/5">
                    <x-badge :classes="$lane['status']->badgeClasses()" :dot="$lane['status']->dotClasses()">
                        {{ $lane['status']->name }}
                    </x-badge>
                    <span class="ml-auto text-xs font-medium text-slate-500 tabular-nums dark:text-slate-400"
                          data-lane-count="{{ $key }}">{{ $lane['tasks']->count() }}</span>
                </header>

                {{-- data-lane の値がドロップ先のステータスになる --}}
                <ul data-lane="{{ $key }}" data-lane-name="{{ $lane['status']->name }}"
                    class="flex min-h-32 flex-1 flex-col gap-2 overflow-y-auto overscroll-contain p-3">
                    @foreach ($lane['tasks'] as $task)
                        <li id="task-{{ $task->id }}" data-task-id="{{ $task->id }}"
                            data-move-url="{{ route('board.move', $task) }}"
                            class="scroll-mt-2 cursor-grab rounded-xl border border-slate-200 bg-white p-3 target:border-brand-500 target:ring-2 target:ring-brand-500/30 active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800">
                            <span class="font-mono text-[11px] tracking-wider text-slate-400 dark:text-slate-500">
                                {{ $task->key() }}
                            </span>
                            <a href="{{ route('tasks.show', $task) }}"
                               class="block text-sm font-medium hover:text-brand-700 dark:hover:text-brand-300">
                                {{ $task->title }}
                            </a>

                            @if ($task->children_count > 0)
                                <div class="mt-2">
                                    <x-progress-bar :done="$task->done_children_count" :total="$task->children_count" compact />
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

                {{--
                    レーンごとの追加フォーム。details/summary を使うことで
                    JavaScript 無しでも開閉できる。ここで追加したタスクは
                    そのレーンのステータスで、末尾に入る。
                --}}
                <details class="shrink-0 border-t border-slate-100 dark:border-white/5"
                         @if ((int) old('status') === $key && $errors->has('quick')) open @endif>
                    <summary title="タスクを追加"
                             class="flex cursor-pointer list-none items-center justify-center py-2 text-slate-400 hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white">
                        <x-icon name="plus" class="size-4" />
                        <span class="sr-only">{{ $lane['status']->name }}に課題を追加</span>
                    </summary>

                    <form action="{{ route('tasks.quick') }}" method="POST" class="px-3 pt-1 pb-3">
                        @csrf
                        <input type="hidden" name="status" value="{{ $key }}">
                        <div class="relative">
                            <input type="text" name="quick" maxlength="200" required
                                   value="{{ (int) old('status') === $key ? old('quick') : '' }}"
                                   placeholder="明日 資料を作る #仕事 !高"
                                   class="field py-2 pr-10 text-sm">
                            <button type="submit" aria-label="追加" title="追加（Enter）"
                                    class="absolute inset-y-1 right-1 grid w-8 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white">
                                <x-icon name="enter" class="size-4" />
                            </button>
                        </div>

                        @if ((int) old('status') === $key)
                            <x-input-error :messages="$errors->get('quick')" />
                        @endif
                    </form>
                </details>
            </section>
        @endforeach
    </div>

    {{-- 許可されていない遷移を試したときの説明。JS が data-board-error に書き込む --}}
    <div data-board-error role="alert"
         class="mt-4 hidden items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">
        <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
        <p data-board-error-message></p>
    </div>

    <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
        ※ ドラッグ＆ドロップには JavaScript が必要です。無効な場合は
        <a href="{{ route('tasks.index') }}" class="underline">課題一覧</a> の編集画面から変更できます。
    </p>
@endsection
