@extends('layouts.app')

@section('title', '課題')

@section('content')
    {{-- 見出しは大きくせず、件数を同じ行に置いて 1 行に収める --}}
    <div class="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h1 class="text-lg font-semibold tracking-tight">課題</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            全 <span class="font-mono tabular-nums">{{ $summary['total'] }}</span> 件のうち
            <span class="font-mono tabular-nums">{{ $tasks->total() }}</span> 件を表示しています。
        </p>
        {{-- エクスポートはいまの絞り込み条件のまま（ページ番号だけ外す） --}}
        <a href="{{ route('tasks.export', request()->except('page')) }}"
           class="ml-auto inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            <x-icon name="download" class="size-3.5" /> CSV エクスポート
        </a>
        <a href="{{ route('tasks.import') }}"
           class="text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            CSV から取り込む
        </a>
        <a href="{{ route('tasks.create') }}"
           class="text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
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

        {{--
            書き方の手引きは常に出しておく。入力中だけ出すと、入力欄は自動でフォーカスされるため
            ほかを押した瞬間に手引きが消えて下の要素がずれ、押したつもりの場所を外してしまう。
        --}}
        <p class="mt-1 text-xs text-slate-400">
            <code class="text-slate-500 dark:text-slate-400">#タグ</code>
            <code class="ml-2 text-slate-500 dark:text-slate-400">!高 / !中 / !低</code>
            <span class="ml-2">日付（明日・来週金曜・3日後・9/25 など）を書くと自動で設定されます</span>
        </p>
    </form>

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
            $filters['q'] ? ['label' => $filters['q'], 'url' => $without('q'), 'mono' => true] : null,
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

    {{-- 保存した条件。押せばその条件の一覧へ。個人ごと --}}
    @if ($savedFilters->isNotEmpty())
        <nav aria-label="保存した条件" class="mb-2 flex flex-wrap items-center gap-1.5">
            <span class="mr-1 text-xs text-slate-500 dark:text-slate-400">保存した条件:</span>
            @foreach ($savedFilters as $saved)
                <span class="inline-flex items-center rounded-sm bg-slate-100 text-xs font-medium text-slate-600 dark:bg-white/5 dark:text-slate-300">
                    <a href="{{ $saved->url() }}" class="py-0.5 pl-1.5 pr-1 hover:underline">{{ $saved->name }}</a>
                    <form action="{{ route('saved-filters.destroy', $saved) }}" method="POST"
                          data-confirm="保存した条件「{{ $saved->name }}」を削除しますか？">
                        @csrf
                        @method('DELETE')
                        <button type="submit" aria-label="保存した条件「{{ $saved->name }}」を削除" title="削除"
                                class="grid h-5 w-5 place-items-center rounded-sm text-slate-400 hover:bg-slate-200 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white">
                            <x-icon name="close" class="size-3" />
                        </button>
                    </form>
                </span>
            @endforeach
        </nav>
    @endif

    {{--
        絞り込み。件数タブ・キーワード・並び替えを 1 行にまとめ、
        残りは「絞り込み」へ畳む。選択のたびに JS で自動送信し、
        JS 無効でも「適用」で送れる。

        畳む仕組みは details ではなく、名前の無いチェックボックス + peer にしている。
        開閉ボタンを行の中に置いたまま、パネルだけを行の下に全幅で出すため。
    --}}
    <form action="{{ route('tasks.index') }}" method="GET" data-auto-submit
          class="surface group mb-3 rounded-md border border-slate-200 p-2 dark:border-slate-800">
        <input type="checkbox" id="filter-more" class="peer sr-only" @checked($foldedCount > 0)>

        <div class="flex flex-wrap items-center gap-2">
            {{-- ステータス名はプロジェクトごとに違うので、タブはカテゴリで絞る --}}
            <nav aria-label="状態で絞り込む"
                 class="flex max-w-full shrink-0 items-center gap-0.5 overflow-x-auto rounded-md bg-slate-100 p-0.5 dark:bg-white/5">
                <x-stat-card label="すべて" :value="$summary['total']"
                             :href="route('tasks.index')"
                             :active="! $filters['status'] && ! $filters['category'] && ! $filters['overdue']" />
                <x-stat-card label="未着手" :value="$summary['todo']" accent="text-slate-600 dark:text-slate-300"
                             :href="route('tasks.index', ['category' => 'todo'])"
                             :active="$filters['category']?->value === 'todo'" />
                <x-stat-card label="進行中" :value="$summary['in_progress']" accent="text-sky-600 dark:text-sky-300"
                             :href="route('tasks.index', ['category' => 'in_progress'])"
                             :active="$filters['category']?->value === 'in_progress'" />
                <x-stat-card label="期限切れ" :value="$summary['overdue']" accent="text-rose-600 dark:text-rose-400"
                             :href="route('tasks.index', ['overdue' => 1])" :active="$filters['overdue']" />
            </nav>

            <label class="relative min-w-48 flex-1">
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

            <label for="filter-more"
                   class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-sm px-2 py-1.5 text-sm text-slate-500 transition select-none hover:bg-slate-100 hover:text-slate-900 group-has-[#filter-more:checked]:bg-slate-100 group-has-[#filter-more:checked]:text-slate-900 group-has-[#filter-more:focus-visible]:ring-2 group-has-[#filter-more:focus-visible]:ring-brand-500 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white dark:group-has-[#filter-more:checked]:bg-white/10 dark:group-has-[#filter-more:checked]:text-white">
                <x-icon name="filter" class="size-4" />
                絞り込み
                @if ($foldedCount > 0)
                    <span class="rounded-sm bg-slate-200 px-1.5 font-mono text-xs font-medium text-slate-700 tabular-nums dark:bg-white/10 dark:text-slate-200">{{ $foldedCount }}</span>
                @endif
            </label>
        </div>

        {{-- 条件が効いているときは、畳んだままでも中身が分かるよう開いておく --}}
        <div class="hidden peer-checked:block">
            <div class="mt-2 space-y-3 border-t border-slate-100 px-1 pt-3 dark:border-white/5">
                {{-- 条件式。選択肢では表せない組み合わせ（自分の担当で 1 週間以内が期限、など）はここに書く --}}
                <div>
                    <label for="filter-q" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">条件式</label>
                    <input id="filter-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="500"
                           placeholder="assignee:me status:進行中 due<7d -tag:後回し"
                           class="field font-mono text-xs">
                    <details class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <summary class="cursor-pointer select-none">書き方</summary>
                        <dl class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-[auto_1fr]">
                            <dt><code>assignee:me</code> / <code>assignee:none</code> / <code>assignee:名前</code></dt><dd>担当者（reporter: で起票者）</dd>
                            <dt><code>status:"レビュー中"</code></dt><dd>ステータス名（空白を含むときは "…" で囲む）</dd>
                            <dt><code>is:open</code> <code>is:done</code> <code>is:overdue</code> <code>is:unassigned</code> <code>is:watching</code></dt><dd>状態</dd>
                            <dt><code>priority:high</code> <code>type:bug</code> <code>tag:名前</code> <code>project:KEY</code></dt><dd>優先度・課題タイプ・タグ・プロジェクト（日本語の名前でも可）</dd>
                            <dt><code>sprint:current</code> / <code>sprint:none</code></dt><dd>進行中のスプリント / バックログ</dd>
                            <dt><code>due&lt;7d</code> <code>due:today</code> <code>created&gt;=-7d</code> <code>updated&lt;2026-10-01</code> <code>due:none</code></dt><dd>日付。7d は 7 日後、-7d は 7 日前</dd>
                            <dt><code>-tag:後回し</code></dt><dd>頭に - で否定</dd>
                            <dt>それ以外の言葉</dt><dd>タイトル・本文の部分一致</dd>
                        </dl>
                    </details>
                </div>

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
        </div>

        @if ($activeFilters->isNotEmpty())
            <div class="mt-2 flex flex-wrap items-center gap-1.5 border-t border-slate-100 px-1 pt-2 dark:border-white/5">
                <span class="mr-1 text-xs text-slate-500 dark:text-slate-400">絞り込み中:</span>
                @foreach ($activeFilters as $filter)
                    {{-- バッジ自体が解除ボタン。1 つずつ外せる --}}
                    <a href="{{ $filter['url'] }}"
                       class="inline-flex items-center gap-1 rounded-sm bg-slate-100 py-0.5 pr-1 pl-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10">
                        <span @class(['font-mono' => $filter['mono'] ?? false])>{{ $filter['label'] }}</span>
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

    {{-- 条件式の読めなかった部分。黙って外すと、絞れていると思い込んだまま結果を読んでしまう --}}
    @if ($queryErrors !== [])
        <div role="alert" class="mb-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            <p class="font-medium">条件式の一部を読めなかったため、その条件は外して表示しています。</p>
            <ul class="mt-1 list-disc pl-5 text-xs">
                @foreach ($queryErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- いまの条件に名前を付けて保存する。検索フォーム（GET）の中には入れ子にできないので外に置く --}}
    @if ($activeFilters->isNotEmpty())
        <form action="{{ route('saved-filters.store') }}" method="POST" class="mb-3 flex flex-wrap items-center justify-end gap-2">
            @csrf
            @foreach (\App\Models\SavedFilter::extract(request()->query()) as $key => $value)
                <input type="hidden" name="query[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <label class="sr-only" for="saved-filter-name">保存する名前</label>
            <input id="saved-filter-name" name="name" type="text" maxlength="40" required value="{{ old('name') }}"
                   placeholder="この条件に名前を付けて保存" class="field w-56 py-1.5 text-xs">
            <button type="submit" class="btn-quiet py-1.5 text-xs">保存</button>
            <div class="w-full text-right"><x-input-error :messages="$errors->get('name')" /></div>
        </form>
    @endif

    {{-- 一覧は箱に入れず、上下の罫線で区切られた領域として置く --}}
    <div class="surface border-y border-slate-200 dark:border-slate-800">
        @if ($tasks->isEmpty())
            <x-empty-state title="該当する課題がありません"
                           description="条件を変えるか、新しい課題を追加してみましょう。">
                <a href="{{ route('tasks.create') }}" class="btn-primary mt-2">
                    <x-icon name="plus" class="size-4" /> 課題を追加
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
