@extends('layouts.app')

@section('title', $task->title)

@section('content')
    {{--
        課題画面は 2 カラム。
        左は「中身」（要約・説明・サブタスク・関連・やりとり）、
        右は「いまの状態」（ステータス・担当者・見積り・日付）と操作。

        1 カラムに積むと、担当者やスプリントを見るだけでスクロールが要る。
        課題画面でいちばん多い用件は「誰が何の状態で持っているか」の確認なので、
        それを常に画面内に置く。

        書き換えはこの画面で完結する。値を押せばその場で入力に変わり、
        保存するとその項目だけが更新される（x-inline-edit）。編集画面へ移動すると
        直したい 1 項目のために全項目のフォームを読み直すことになる。
    --}}
    <nav aria-label="パンくず" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('tasks.index') }}"
           class="inline-flex items-center gap-1 text-slate-500 transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">
            <x-icon name="arrow-left" class="size-4" /> 一覧
        </a>
        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold tracking-wider text-slate-600 dark:bg-white/5 dark:text-slate-300">
            {{ $task->project->key }}
        </span>

        {{-- 親がいるなら、その場で親へ戻れるようにしておく --}}
        @if ($task->isChild())
            <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
            <a href="{{ route('tasks.show', $task->parent) }}"
               class="font-mono text-xs tracking-wider text-slate-500 underline transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">
                {{ $task->parent->key() }}
            </a>
        @endif

        <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
        <span class="font-mono text-xs tracking-wider text-slate-500 dark:text-slate-400">{{ $task->key() }}</span>
    </nav>

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        {{-- 左：課題の中身 -------------------------------------------------- --}}
        <div class="min-w-0 space-y-4">
            <article class="card overflow-hidden">
                <div class="px-5 py-5 sm:px-6">
                    {{-- 見出しは課題キーではなく要約。キーはパンくずに常駐している --}}
                    <div class="flex items-start gap-2.5">
                        <x-issue-type-mark :type="$task->issue_type" class="mt-1.5 size-6" />

                        <x-inline-edit class="min-w-0 flex-1" :editable="$canUpdate"
                                       :action="route('tasks.title', $task)" field="title" label="タイトル"
                                       trigger-class="-mx-2 items-start px-2 py-1">
                            <x-slot:display>
                                <h1 class="min-w-0 flex-1 text-xl font-bold break-words {{ $task->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
                                    {{ $task->title }}
                                </h1>
                            </x-slot:display>

                            <input name="title" type="text" required maxlength="100" autocomplete="off"
                                   value="{{ old('title', $task->title) }}"
                                   class="field text-base font-bold @error('title') border-rose-400 @enderror">
                            <x-input-error :messages="$errors->get('title')" />
                        </x-inline-edit>
                    </div>

                    {{--
                        タグも押せば付け替えられる。付いていないときは「タグなし」と置いて
                        押せる場所を示すが、直せない相手には空の行を見せても仕方がないので畳む。
                    --}}
                    @if ($canUpdate || $task->tags->isNotEmpty())
                    <div class="mt-2 pl-8.5">
                        <x-inline-edit :editable="$canUpdate" :action="route('tasks.tags', $task)"
                                       field="tags" label="タグ" trigger-class="-mx-2 flex-wrap px-2 py-1">
                            <x-slot:display>
                                @forelse ($task->tags as $tag)
                                    <x-badge :classes="$tag->color->badgeClasses()" :dot="$tag->color->swatchClasses()">
                                        {{ $tag->name }}
                                    </x-badge>
                                @empty
                                    <span class="text-xs text-slate-400 dark:text-slate-500">タグなし</span>
                                @endforelse
                            </x-slot:display>

                            <x-tag-picker :tags="$tags" :selected="old('tags', $task->tags->pluck('id')->all())" />
                        </x-inline-edit>
                    </div>
                    @endif
                </div>

                <div class="border-t border-slate-100 px-5 py-5 sm:px-6 dark:border-white/5">
                    <h2 class="mb-2 text-sm font-semibold">説明</h2>

                    <x-inline-edit :editable="$canUpdate" :action="route('tasks.content', $task)"
                                   field="content" label="説明" trigger-class="-mx-2 items-start px-2 py-1">
                        <x-slot:display>
                            <div class="min-w-0 flex-1">
                                @if ($task->content)
                                    {{-- 保存時に許可タグだけへサニタイズ済みなので、そのまま描画する --}}
                                    <div class="prose-content">{!! \App\Support\RichText::forDisplay($task->content) !!}</div>
                                @else
                                    <p class="text-sm text-slate-400 dark:text-slate-500">
                                        {{ $canUpdate ? 'クリックして説明を書く' : '内容は登録されていません。' }}
                                    </p>
                                @endif
                            </div>
                        </x-slot:display>

                        <x-rich-editor :value="old('content', $task->content)" />
                        <x-input-error :messages="$errors->get('content')" />
                    </x-inline-edit>
                </div>
            </article>

            {{-- サブタスク --}}
            <section class="card px-5 py-5 sm:px-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold">サブタスク</h2>
                    @if ($task->children->isNotEmpty())
                        <span class="text-xs text-slate-500 tabular-nums dark:text-slate-400">
                            {{ $task->children->filter(fn ($child) => $child->isCompleted())->count() }}/{{ $task->children->count() }} 完了
                        </span>
                    @endif
                </div>

                @if ($task->children->isNotEmpty())
                    <div class="mb-4">
                        <x-progress-bar :done="$task->children->filter(fn ($child) => $child->isCompleted())->count()"
                                        :total="$task->children->count()" />
                    </div>

                    <ul class="mb-4 divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($task->children as $subtask)
                            <x-subtask-row :parent="$task" :subtask="$subtask" />
                        @endforeach
                    </ul>
                @endif

                {{--
                    1 つの入力欄で 2 つのことができる。
                    課題キー（{{ $task->project->key }}-12）や詳細画面の URL を貼れば既存の課題を
                    サブタスクにし、それ以外はタイトルとして新しく作る。
                --}}
                <form action="{{ route('subtasks.store', $task) }}" method="POST" class="relative">
                    @csrf
                    <input type="text" name="title" maxlength="255" required
                           value="{{ old('title') }}"
                           placeholder="サブタスクを追加、または {{ $task->project->key }}-12 / URL を貼って既存の課題を紐づけ"
                           class="field py-2 pr-10 text-sm">
                    <button type="submit" aria-label="サブタスクを追加" title="追加（Enter）"
                            class="absolute inset-y-1 right-1 grid w-8 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white">
                        <x-icon name="enter" class="size-4" />
                    </button>
                </form>
                <x-input-error :messages="$errors->get('title')" />
            </section>

            @include('tasks.links')

            @include('tasks.timeline')
        </div>

        {{-- 右：いまの状態と操作 ---------------------------------------------- --}}
        <aside class="space-y-4 lg:sticky lg:top-6">
            @can('update', $task)
                <div class="card space-y-4 p-4">
                    {{--
                        ステータスは「いまの状態」を主役にし、行ける先をその中に畳む。
                        遷移ボタンを平らに並べると、現在地がどれなのかが読み取れない。
                        details なので JS 無しでも開閉できる。
                    --}}
                    <div>
                        <p class="mb-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">ステータス</p>
                        <x-menu align="left" width="w-64" class="w-full">
                            <x-slot:trigger>
                                <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-sm font-semibold ring-1 ring-inset {{ $task->status->badgeClasses() }}">
                                    <span class="size-1.5 rounded-full {{ $task->status->dotClasses() }}"></span>
                                    {{ $task->status->name }}
                                </span>
                            </x-slot:trigger>

                            @forelse ($transitions as $status)
                                <form action="{{ route('tasks.transition', $task) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status->id }}">
                                    <button type="submit"
                                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition hover:bg-slate-100 dark:hover:bg-white/5">
                                        <span class="size-1.5 shrink-0 rounded-full {{ $status->dotClasses() }}"></span>
                                        {{ $status->name }}
                                    </button>
                                </form>
                            @empty
                                <p class="px-3 py-2 text-xs text-slate-400 dark:text-slate-500">
                                    このステータスからは移動できません。
                                </p>
                            @endforelse
                        </x-menu>
                        <x-input-error :messages="$errors->get('status')" />
                    </div>

                    {{-- 担当者。候補はこのプロジェクトのメンバーだけ --}}
                    <div>
                        <label for="assignee" class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">担当者</label>
                        <div class="flex items-center gap-2">
                            <x-avatar :user="$task->assignee" size="md" />

                            <form action="{{ route('tasks.assignee', $task) }}" method="POST" data-auto-submit
                                  class="flex min-w-0 flex-1 items-center gap-1">
                                @csrf
                                @method('PATCH')
                                <select id="assignee" name="assignee" class="field min-w-0 flex-1 px-2 py-1.5 text-sm">
                                    <option value="">未割り当て</option>
                                    @foreach ($members as $member)
                                        <option value="{{ $member->id }}" @selected($task->assignee_id === $member->id)>{{ $member->name }}</option>
                                    @endforeach
                                </select>
                                {{-- JS が動いていれば選んだ時点で送られるので、この控えは隠れる --}}
                                <button type="submit" data-submit-fallback
                                        class="shrink-0 rounded-lg px-2 py-1 text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                                    変更
                                </button>
                            </form>
                        </div>

                        @if ($task->assignee_id !== auth()->id())
                            {{-- よく使う操作なので 1 クリックで済ませられるようにする --}}
                            <form action="{{ route('tasks.assignee', $task) }}" method="POST" class="mt-1.5">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="assignee" value="{{ auth()->id() }}">
                                <button type="submit"
                                        class="text-xs text-brand-700 underline transition hover:text-brand-800 dark:text-brand-300 dark:hover:text-brand-200">
                                    自分に割り当てる
                                </button>
                            </form>
                        @endif
                        <x-input-error :messages="$errors->get('assignee')" />
                    </div>

                    {{-- 課題タイプ。親子関係とは別の軸なので、サブタスクのままでも変えられる --}}
                    <div>
                        <label for="issue_type" class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">課題タイプ</label>
                        <div class="flex items-center gap-2">
                            <x-icon :name="$task->issue_type->icon()"
                                    class="size-5 shrink-0 {{ $task->issue_type->iconClasses() }}" />

                            <form action="{{ route('tasks.type', $task) }}" method="POST" data-auto-submit
                                  class="flex min-w-0 flex-1 items-center gap-1">
                                @csrf
                                @method('PATCH')
                                <select id="issue_type" name="issue_type" class="field min-w-0 flex-1 px-2 py-1.5 text-sm">
                                    @foreach (\App\Enums\IssueType::options() as $value => $label)
                                        <option value="{{ $value }}" @selected($task->issue_type->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" data-submit-fallback
                                        class="shrink-0 rounded-lg px-2 py-1 text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                                    変更
                                </button>
                            </form>
                        </div>
                        <x-input-error :messages="$errors->get('issue_type')" />
                    </div>
                </div>
            @endcan

            {{--
                値を押せばその場で直せる項目。
                スプリントと起票者だけは読むだけ（移送は SprintService、起票者は不変）。
            --}}
            <dl class="card divide-y divide-slate-100 text-sm dark:divide-white/5">
                <div class="flex items-start gap-3 px-4 py-2">
                    <dt class="py-1 text-slate-500 dark:text-slate-400">優先度</dt>
                    <dd class="ml-auto min-w-0 flex-1">
                        <x-inline-edit :editable="$canUpdate" :action="route('tasks.priority', $task)"
                                       field="priority" label="優先度" trigger-class="-mr-2 justify-end py-1 pr-2">
                            <x-slot:display>
                                <span class="flex items-center gap-1.5 font-medium">
                                    <x-priority-mark :priority="$task->priority" />
                                    {{ $task->priority->label() }}
                                </span>
                            </x-slot:display>

                            <select name="priority" class="field px-2 py-1.5 text-sm">
                                @foreach (\App\Enums\TaskPriority::options() as $value => $label)
                                    <option value="{{ $value }}"
                                            @selected(old('priority', $task->priority->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('priority')" />
                        </x-inline-edit>
                    </dd>
                </div>

                <div class="flex items-start gap-3 px-4 py-2">
                    <dt class="py-1 text-slate-500 dark:text-slate-400">期限</dt>
                    <dd class="ml-auto min-w-0 flex-1">
                        <x-inline-edit :editable="$canUpdate" :action="route('tasks.due-date', $task)"
                                       field="due_date" label="期限" trigger-class="-mr-2 justify-end py-1 pr-2">
                            <x-slot:display>
                                @if ($task->due_date)
                                    <x-due-date :issue="$task" class="!text-sm font-medium" />
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">未設定</span>
                                @endif
                            </x-slot:display>

                            {{-- 空にして保存すれば未設定に戻せる --}}
                            <input name="due_date" type="date" value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}"
                                   class="field px-2 py-1.5 text-sm">
                            <x-input-error :messages="$errors->get('due_date')" />
                        </x-inline-edit>
                    </dd>
                </div>

                <div class="flex items-center gap-3 px-4 py-2.5">
                    <dt class="text-slate-500 dark:text-slate-400">スプリント</dt>
                    <dd class="ml-auto font-medium">{{ $task->sprint?->name ?? 'バックログ' }}</dd>
                </div>

                <div class="flex items-start gap-3 px-4 py-2">
                    <dt class="py-1 text-slate-500 dark:text-slate-400">ストーリーポイント</dt>
                    <dd class="ml-auto min-w-0 flex-1">
                        <x-inline-edit :editable="$canUpdate" :action="route('tasks.story-points', $task)"
                                       field="story_points" label="ストーリーポイント"
                                       trigger-class="-mr-2 justify-end py-1 pr-2">
                            <x-slot:display>
                                <span class="font-medium tabular-nums">{{ $task->story_points ?? '—' }}</span>
                            </x-slot:display>

                            <input name="story_points" type="number" min="0" max="999" step="1" inputmode="numeric"
                                   value="{{ old('story_points', $task->story_points) }}" placeholder="未設定"
                                   class="field px-2 py-1.5 text-sm">
                            <x-input-error :messages="$errors->get('story_points')" />
                        </x-inline-edit>
                    </dd>
                </div>

                <div class="flex items-center gap-3 px-4 py-2.5">
                    <dt class="text-slate-500 dark:text-slate-400">起票者</dt>
                    <dd class="ml-auto flex items-center gap-2 font-medium">
                        <x-avatar :user="$task->reporter" label="起票" />
                        {{ $task->reporter?->name ?? '—' }}
                    </dd>
                </div>

                {{-- 日付は参照頻度が低いので、色も文字も落として最後にまとめる --}}
                <div class="space-y-1 px-4 py-2.5 text-xs text-slate-500 dark:text-slate-400">
                    <p class="flex items-center gap-3">
                        <span>作成</span>
                        <span class="ml-auto">{{ $task->created_at->isoFormat('YYYY/M/D HH:mm') }}</span>
                    </p>
                    <p class="flex items-center gap-3">
                        <span>更新</span>
                        <span class="ml-auto">{{ $task->updated_at->diffForHumans() }}</span>
                    </p>
                    @if ($task->completed_at)
                        <p class="flex items-center gap-3">
                            <span>完了</span>
                            <span class="ml-auto">{{ $task->completed_at->isoFormat('YYYY/M/D HH:mm') }}</span>
                        </p>
                    @endif
                </div>
            </dl>

            {{--
                編集への入り口は置かない。項目はすべてその場で直せるので、
                ここに残るのは「その場では済まない操作」＝削除だけ。
            --}}
            <div class="flex items-center gap-2">
                @can('delete', $task)
                    <form action="{{ route('tasks.destroy', $task) }}" method="POST" class="ml-auto"
                          data-confirm="「{{ $task->title }}」を削除します。よろしいですか？">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                            <x-icon name="trash" class="size-4" /> 削除
                        </button>
                    </form>
                @endcan
            </div>
        </aside>
    </div>
@endsection
