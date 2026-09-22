@extends('layouts.app')

@section('title', 'タスク一覧')

@section('content')
    {{-- 見出しは大きくせず、件数を同じ行に置いて 1 行に収める --}}
    <div class="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h1 class="text-base font-semibold tracking-tight">タスク一覧</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            全 <span class="font-mono tabular-nums">{{ $summary['total'] }}</span> 件のうち
            <span class="font-mono tabular-nums">{{ $tasks->total() }}</span> 件を表示しています。
        </p>
        <a href="{{ route('tasks.create') }}"
           class="ml-auto text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            詳しく入力して作成 →
        </a>
    </div>

    {{--
        クイック追加。1 行に書いた期限・タグ・優先度をサーバー側で解釈する。
        JS に依存しない通常のフォーム送信で完結させている。
    --}}
    <form action="{{ route('tasks.quick') }}" method="POST" class="mb-3">
        @csrf
        <div class="relative">
            <input type="text" name="quick" value="{{ old('quick') }}" maxlength="200" required autofocus
                   placeholder="明日 請求書を送る #仕事 !高"
                   class="field pr-10 @error('quick') border-rose-400 @enderror">
            {{-- 送信は入力欄の中に置く。Enter でも送れることをアイコンで示す --}}
            <button type="submit" aria-label="追加" title="追加（Enter）"
                    class="absolute inset-y-1 right-1 grid w-8 place-items-center rounded-sm text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white">
                <x-icon name="enter" class="size-4" />
            </button>
        </div>

        <x-input-error :messages="$errors->get('quick')" />

        <p class="mt-1 text-xs text-slate-400">
            <code class="text-slate-500 dark:text-slate-400">#タグ</code>
            <code class="ml-2 text-slate-500 dark:text-slate-400">!高 / !中 / !低</code>
            <span class="ml-2">日付（明日・来週金曜・3日後・9/25 など）を書くと自動で設定されます</span>
        </p>
    </form>

    {{-- 件数の升目。クリックでそのままフィルタとしても働く --}}
    <div class="surface mb-3 grid grid-cols-2 divide-x divide-y divide-slate-200 overflow-hidden rounded-md border border-slate-200 sm:grid-cols-4 sm:divide-y-0 dark:divide-slate-800 dark:border-slate-800">
        <x-stat-card label="すべて" :value="$summary['total']"
                     :href="route('tasks.index')"
                     :active="! $filters['status'] && ! $filters['category'] && ! $filters['overdue']" />
        {{-- ステータス名はプロジェクトごとに違うので、カードはカテゴリで絞る --}}
        <x-stat-card label="未着手" :value="$summary['todo']" accent="text-slate-600 dark:text-slate-300"
                     :href="route('tasks.index', ['category' => 'todo'])"
                     :active="$filters['category']?->value === 'todo'" />
        <x-stat-card label="進行中" :value="$summary['in_progress']" accent="text-sky-600 dark:text-sky-300"
                     :href="route('tasks.index', ['category' => 'in_progress'])"
                     :active="$filters['category']?->value === 'in_progress'" />
        <x-stat-card label="期限切れ" :value="$summary['overdue']" accent="text-rose-600 dark:text-rose-400"
                     :href="route('tasks.index', ['overdue' => 1])" :active="$filters['overdue']" />
    </div>

    @php
        /*
         * いま効いている条件を、外せるバッジとして並べるための材料。
         *
         * 細かい絞り込みは下のパネルに畳んであるので、畳んだままでも
         * 「何で絞られているか」は必ず見えている必要がある。
         * 外す URL は該当のキーだけを落として作る（他の条件は残す）。
         */
        $without = fn (string $key) => request()->fullUrlWithoutQuery([$key, 'page']);

        $activeFilters = collect([
            $filters['keyword'] ? ['label' => '「'.$filters['keyword'].'」', 'url' => $without('keyword')] : null,
            $filters['project'] ? ['label' => $projects->firstWhere('id', $filters['project'])?->key, 'url' => $without('project')] : null,
            $filters['category'] ? ['label' => $filters['category']->label(), 'url' => $without('category')] : null,
            $filters['status'] ? ['label' => $statuses->firstWhere('id', $filters['status'])?->name, 'url' => $without('status')] : null,
            $filters['priority'] ? ['label' => '優先度'.$filters['priority']->label(), 'url' => $without('priority')] : null,
            $filters['tag'] ? ['label' => $tags->firstWhere('id', $filters['tag'])?->name, 'url' => $without('tag')] : null,
            $filters['overdue'] ? ['label' => '期限切れのみ', 'url' => $without('overdue')] : null,
        ])->filter(fn (?array $filter) => $filter !== null && $filter['label'] !== null);

        // キーワードと並び替えは常に見えているので、畳んだ中にある条件だけを数える
        $foldedCount = $activeFilters->count() - ($filters['keyword'] ? 1 : 0);
    @endphp

    {{--
        絞り込み。よく使うキーワードと並び替えだけを常時出し、
        残りは「詳細な絞り込み」へ畳む。選択のたびに JS で自動送信し、
        JS 無効でも「適用」で送れる。
    --}}
    <form action="{{ route('tasks.index') }}" method="GET" data-auto-submit
          class="surface mb-3 rounded-md border border-slate-200 p-2.5 dark:border-slate-800">
        <div class="flex flex-wrap items-center gap-2">
            <label class="relative min-w-52 flex-1">
                <span class="sr-only">キーワード検索</span>
                <x-icon name="search" class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="keyword" value="{{ $filters['keyword'] }}"
                       placeholder="タイトル・内容を検索" class="field pl-9">
            </label>

            <label class="shrink-0">
                <span class="sr-only">並び替え</span>
                <select name="sort" class="field w-auto">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        {{-- 条件が効いているときは、畳んだままでも中身が分かるよう開いておく --}}
        <details class="mt-3" @if ($foldedCount > 0) open @endif>
            <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 text-sm text-slate-500 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                <x-icon name="filter" class="size-4" />
                詳細な絞り込み
                @if ($foldedCount > 0)
                    <span class="rounded-sm bg-slate-200 px-1.5 font-mono text-xs font-medium text-slate-700 tabular-nums dark:bg-white/10 dark:text-slate-200">{{ $foldedCount }}</span>
                @endif
            </summary>

            <div class="mt-3 space-y-3 border-t border-slate-100 pt-3 dark:border-white/5">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @if ($projects->count() > 1)
                        {{-- 一覧は横断ビューのまま。プロジェクトは絞り込みの 1 つとして足す --}}
                        <label>
                            <span class="sr-only">プロジェクト</span>
                            <select name="project" class="field">
                                <option value="">プロジェクト：すべて</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected($filters['project'] === $project->id)>
                                        {{ $project->key }} — {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label>
                        <span class="sr-only">ステータス</span>
                        <select name="status" class="field">
                            <option value="">ステータス：すべて</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected($filters['status'] === $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    {{-- ラジオではなく選択にする。ラジオだと一度選んだ優先度を単独で外せない --}}
                    <label>
                        <span class="sr-only">優先度</span>
                        <select name="priority" class="field">
                            <option value="">優先度：すべて</option>
                            @foreach (\App\Enums\TaskPriority::options() as $value => $label)
                                <option value="{{ $value }}" @selected($filters['priority']?->value === $value)>優先度{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @if ($tags->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="mr-1 text-xs text-slate-500 dark:text-slate-400">タグ:</span>
                        {{-- ラジオなので、同じタグを再度押す代わりに「すべて」で解除する --}}
                        <label class="cursor-pointer">
                            <input type="radio" name="tag" value="" class="peer sr-only" @checked(! $filters['tag'])>
                            <span class="chip text-slate-500 ring-slate-200 transition peer-checked:bg-slate-900 peer-checked:text-white dark:text-slate-400 dark:ring-slate-700 dark:peer-checked:bg-white dark:peer-checked:text-slate-900">
                                すべて
                            </span>
                        </label>
                        @foreach ($tags as $tag)
                            <label class="cursor-pointer">
                                <input type="radio" name="tag" value="{{ $tag->id }}" class="peer sr-only"
                                       @checked($filters['tag'] === $tag->id)>
                                <span class="chip opacity-60 grayscale transition peer-checked:opacity-100 peer-checked:grayscale-0 {{ $tag->color->badgeClasses() }}">
                                    <span class="size-1.5 rounded-full {{ $tag->color->swatchClasses() }}"></span>{{ $tag->name }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="overdue" value="1" @checked($filters['overdue'])
                               class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                        期限切れのみ
                    </label>

                    {{-- 集計カードから来た絞り込みも、パネルを開いたときに引き継ぐ --}}
                    @if ($filters['category'])
                        <input type="hidden" name="category" value="{{ $filters['category']->value }}">
                    @endif

                    <button type="submit" class="btn-quiet ml-auto">適用</button>
                </div>
            </div>
        </details>

        @if ($activeFilters->isNotEmpty())
            <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3 dark:border-white/5">
                <span class="mr-1 text-xs text-slate-500 dark:text-slate-400">絞り込み中:</span>
                @foreach ($activeFilters as $filter)
                    {{-- バッジ自体が解除ボタン。1 つずつ外せる --}}
                    <a href="{{ $filter['url'] }}"
                       class="inline-flex items-center gap-1 rounded-sm bg-slate-100 py-0.5 pr-1 pl-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10">
                        {{ $filter['label'] }}
                        <x-icon name="close" class="size-3.5 text-slate-400" />
                        <span class="sr-only">この条件を外す</span>
                    </a>
                @endforeach

                @if ($activeFilters->count() > 1)
                    <a href="{{ route('tasks.index') }}"
                       class="ml-1 text-xs text-slate-500 underline transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                        すべて解除
                    </a>
                @endif
            </div>
        @endif
    </form>

    {{-- 一覧は箱に入れず、上下の罫線で区切られた領域として置く --}}
    <div class="surface border-y border-slate-200 dark:border-slate-800">
        @if ($tasks->isEmpty())
            <x-empty-state title="該当するタスクがありません"
                           description="条件を変えるか、新しいタスクを追加してみましょう。">
                <a href="{{ route('tasks.create') }}" class="btn-primary mt-2">
                    <x-icon name="plus" class="size-4" /> タスクを追加
                </a>
            </x-empty-state>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-white/10">
                @foreach ($tasks as $task)
                    <x-task-card :task="$task" />
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-3">
        {{ $tasks->links() }}
    </div>
@endsection
