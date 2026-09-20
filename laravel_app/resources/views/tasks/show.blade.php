@extends('layouts.app')

@section('title', $task->title)

@section('content')
    <x-page-heading title="タスク詳細" :back="route('tasks.index')" back-label="一覧に戻る" />

    <article class="card animate-rise overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-5 sm:px-6 dark:border-white/5">
            <div class="flex flex-wrap items-center gap-1.5">
                <x-badge :classes="$task->status->badgeClasses()">{{ $task->status->label() }}</x-badge>
                <x-badge :classes="$task->priority->badgeClasses()" :dot="$task->priority->dotClasses()">
                    優先度{{ $task->priority->label() }}
                </x-badge>
                @if ($task->isOverdue())
                    <x-badge classes="bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30">
                        <x-icon name="alert" class="size-3.5" /> 期限切れ
                    </x-badge>
                @endif
            </div>
            <h2 class="mt-3 text-xl font-bold break-words {{ $task->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
                {{ $task->title }}
            </h2>
        </div>

        <div class="px-5 py-5 sm:px-6">
            @if ($task->content)
                <p class="leading-relaxed whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $task->content }}</p>
            @else
                <p class="text-sm text-slate-400 dark:text-slate-500">内容は登録されていません。</p>
            @endif
        </div>

        <dl class="grid gap-px overflow-hidden border-t border-slate-100 bg-slate-100 sm:grid-cols-2 dark:border-white/5 dark:bg-white/5">
            @foreach ([
                ['期限', $task->due_date?->isoFormat('YYYY年M月D日(ddd)') ?? '未設定', 'calendar'],
                ['完了日時', $task->completed_at?->isoFormat('YYYY年M月D日 HH:mm') ?? '—', 'check'],
                ['作成日時', $task->created_at->isoFormat('YYYY年M月D日 HH:mm'), 'sparkles'],
                ['更新日時', $task->updated_at->diffForHumans(), 'clock'],
            ] as [$label, $value, $icon])
                <div class="flex items-center gap-3 bg-white px-5 py-3.5 sm:px-6 dark:bg-slate-900/70">
                    <x-icon :name="$icon" class="size-4 shrink-0 text-slate-400" />
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                    <dd class="ml-auto text-sm font-medium">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-5 py-4 sm:px-6 dark:border-white/5">
            <form action="{{ route('tasks.completion', $task) }}" method="POST">
                @csrf
                @method('PATCH')
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-medium transition
                               {{ $task->isCompleted()
                                    ? 'border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-white/5'
                                    : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                    <x-icon name="check" class="size-4" />
                    {{ $task->isCompleted() ? '未着手に戻す' : '完了にする' }}
                </button>
            </form>

            <a href="{{ route('tasks.edit', $task) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium transition hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-white/5">
                <x-icon name="pencil" class="size-4" /> 編集
            </a>

            <form action="{{ route('tasks.destroy', $task) }}" method="POST" class="ml-auto"
                  data-confirm="「{{ $task->title }}」を削除します。よろしいですか？">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                    <x-icon name="trash" class="size-4" /> 削除
                </button>
            </form>
        </div>
    </article>
@endsection
