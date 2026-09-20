@extends('layouts.app')

@section('title', 'タスク一覧')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">タスク一覧</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                全 {{ $summary['total'] }} 件のうち {{ $tasks->total() }} 件を表示しています。
            </p>
        </div>
        <a href="{{ route('tasks.create') }}"
           class="text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            詳しく入力して作成 →
        </a>
    </div>

    {{--
        クイック追加。1 行に書いた期限・タグ・優先度をサーバー側で解釈する。
        JS に依存しない通常のフォーム送信で完結させている。
    --}}
    <form action="{{ route('tasks.quick') }}" method="POST" class="mb-5">
        @csrf
        <div class="relative">
            <input type="text" name="quick" value="{{ old('quick') }}" maxlength="200" required autofocus
                   placeholder="明日 請求書を送る #仕事 !高"
                   class="field pr-11 @error('quick') border-rose-400 @enderror">
            {{-- 送信は入力欄の中に置く。Enter でも送れることをアイコンで示す --}}
            <button type="submit" aria-label="追加" title="追加（Enter）"
                    class="absolute inset-y-1 right-1 grid w-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white">
                <x-icon name="enter" class="size-4" />
            </button>
        </div>

        <x-input-error :messages="$errors->get('quick')" />

        <p class="mt-1.5 text-xs text-slate-400">
            <code class="text-slate-500 dark:text-slate-400">#タグ</code>
            <code class="ml-2 text-slate-500 dark:text-slate-400">!高 / !中 / !低</code>
            <span class="ml-2">日付（明日・来週金曜・3日後・9/25 など）を書くと自動で設定されます</span>
        </p>
    </form>

    {{-- 集計カード。クリックでそのままフィルタとしても働く --}}
    <div class="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <x-stat-card label="すべて" :value="$summary['total']"
                     :href="route('tasks.index')" :active="! $filters['status'] && ! $filters['overdue']" />
        <x-stat-card label="未着手" :value="$summary['todo']" accent="text-slate-600 dark:text-slate-300"
                     :href="route('tasks.index', ['status' => 'todo'])" :active="$filters['status']?->value === 'todo'" />
        <x-stat-card label="進行中" :value="$summary['doing']" accent="text-sky-600 dark:text-sky-300"
                     :href="route('tasks.index', ['status' => 'doing'])" :active="$filters['status']?->value === 'doing'" />
        <x-stat-card label="期限切れ" :value="$summary['overdue']" accent="text-rose-600 dark:text-rose-400"
                     :href="route('tasks.index', ['overdue' => 1])" :active="$filters['overdue']" />
    </div>

    {{-- 絞り込みフォーム。選択のたびに JS で自動送信し、JS 無効でも「適用」で送れる --}}
    <form action="{{ route('tasks.index') }}" method="GET" data-auto-submit class="card mb-4 p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="relative sm:col-span-2">
                <span class="sr-only">キーワード検索</span>
                <x-icon name="search" class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="keyword" value="{{ $filters['keyword'] }}"
                       placeholder="タイトル・内容を検索" class="field pl-9">
            </label>

            <label>
                <span class="sr-only">ステータス</span>
                <select name="status" class="field">
                    <option value="">ステータス：すべて</option>
                    @foreach (\App\Enums\TaskStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status']?->value === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="sr-only">並び替え</span>
                <select name="sort" class="field">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($tags->isNotEmpty())
            <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3 dark:border-white/5">
                <span class="mr-1 text-xs text-slate-500 dark:text-slate-400">タグ:</span>
                {{-- ラジオなので、同じタグを再度押す代わりに「すべて」で解除する --}}
                <label class="cursor-pointer">
                    <input type="radio" name="tag" value="" class="peer sr-only" @checked(! $filters['tag'])>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium text-slate-500 ring-1 ring-slate-200 ring-inset transition peer-checked:bg-slate-900 peer-checked:text-white dark:text-slate-400 dark:ring-slate-700 dark:peer-checked:bg-white dark:peer-checked:text-slate-900">
                        すべて
                    </span>
                </label>
                @foreach ($tags as $tag)
                    <label class="cursor-pointer">
                        <input type="radio" name="tag" value="{{ $tag->id }}" class="peer sr-only"
                               @checked($filters['tag'] === $tag->id)>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition
                                     opacity-60 grayscale peer-checked:opacity-100 peer-checked:grayscale-0 {{ $tag->color->badgeClasses() }}">
                            <span class="size-1.5 rounded-full {{ $tag->color->swatchClasses() }}"></span>{{ $tag->name }}
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        <div class="mt-3 flex flex-wrap items-center gap-3">
            @foreach (\App\Enums\TaskPriority::options() as $value => $label)
                <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                    <input type="radio" name="priority" value="{{ $value }}"
                           @checked($filters['priority']?->value === $value)
                           class="size-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                    優先度{{ $label }}
                </label>
            @endforeach

            <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="overdue" value="1" @checked($filters['overdue'])
                       class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                期限切れのみ
            </label>

            <div class="ml-auto flex gap-2">
                @if ($filters['keyword'] || $filters['status'] || $filters['priority'] || $filters['tag'] || $filters['overdue'])
                    <a href="{{ route('tasks.index') }}"
                       class="rounded-xl px-3 py-2 text-sm text-slate-500 transition hover:bg-slate-100 dark:hover:bg-white/5">条件をクリア</a>
                @endif
                <button type="submit"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium transition hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-white/5">
                    適用
                </button>
            </div>
        </div>
    </form>

    <div class="card overflow-hidden">
        @if ($tasks->isEmpty())
            <x-empty-state title="該当するタスクがありません"
                           description="条件を変えるか、新しいタスクを追加してみましょう。">
                <a href="{{ route('tasks.create') }}"
                   class="mt-2 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-700">
                    <x-icon name="plus" class="size-4" /> タスクを追加
                </a>
            </x-empty-state>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($tasks as $task)
                    <x-task-card :task="$task" />
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-4">
        {{ $tasks->links() }}
    </div>
@endsection
