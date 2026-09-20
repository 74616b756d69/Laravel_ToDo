@extends('layouts.app')

@section('title', $task->title)

@section('content')
    <x-page-heading :title="$task->key()" :back="route('tasks.index')" back-label="一覧に戻る" />

    <article class="card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-5 sm:px-6 dark:border-white/5">
            <div class="flex flex-wrap items-center gap-1.5">
                <x-badge :classes="$task->issue_type->badgeClasses()">{{ $task->issue_type->label() }}</x-badge>
                <x-badge :classes="$task->status->badgeClasses()">{{ $task->status->name }}</x-badge>
                <x-badge :classes="$task->priority->badgeClasses()" :dot="$task->priority->dotClasses()">
                    優先度{{ $task->priority->label() }}
                </x-badge>
                @if ($task->isOverdue())
                    <x-badge classes="bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30">
                        <x-icon name="alert" class="size-3.5" /> 期限切れ
                    </x-badge>
                @endif

                @foreach ($task->tags as $tag)
                    <x-badge :classes="$tag->color->badgeClasses()" :dot="$tag->color->swatchClasses()">{{ $tag->name }}</x-badge>
                @endforeach
            </div>
            <h2 class="mt-3 text-xl font-bold break-words {{ $task->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
                {{ $task->title }}
            </h2>
        </div>

        <div class="px-5 py-5 sm:px-6">
            @if ($task->content)
                {{-- 保存時に許可タグだけへサニタイズ済みなので、そのまま描画する --}}
                <div class="prose-content">{!! \App\Support\RichText::forDisplay($task->content) !!}</div>
            @else
                <p class="text-sm text-slate-400 dark:text-slate-500">内容は登録されていません。</p>
            @endif
        </div>

        {{-- サブタスク --}}
        <section class="border-t border-slate-100 px-5 py-5 sm:px-6 dark:border-white/5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold">サブタスク</h3>
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

                <ul class="mb-4 space-y-1">
                    @foreach ($task->children as $subtask)
                        <li class="group/sub flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                            <form action="{{ route('subtasks.toggle', [$task, $subtask]) }}" method="POST" class="flex">
                                @csrf
                                @method('PATCH')
                                <button type="submit" aria-label="{{ $subtask->isCompleted() ? '未完了に戻す' : '完了にする' }}"
                                        class="grid size-4.5 place-items-center rounded border transition
                                               {{ $subtask->isCompleted()
                                                    ? 'border-brand-500 bg-brand-500 text-white'
                                                    : 'border-slate-300 text-transparent hover:border-brand-500 dark:border-slate-600' }}">
                                    <x-icon name="check" class="size-3" stroke-width="3" />
                                </button>
                            </form>

                            {{-- 引き込んだ既存課題は独立した課題なので、詳細へ行けるようにする --}}
                            @if ($subtask->issue_type->isSubtask())
                                <span class="flex-1 text-sm {{ $subtask->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
                                    {{ $subtask->title }}
                                </span>
                            @else
                                <span class="shrink-0 font-mono text-[11px] tracking-wider text-slate-400 dark:text-slate-500">
                                    {{ $subtask->key() }}
                                </span>
                                <x-badge :classes="$subtask->issue_type->badgeClasses()">
                                    {{ $subtask->issue_type->label() }}
                                </x-badge>
                                <a href="{{ route('tasks.show', $subtask) }}"
                                   class="flex-1 truncate text-sm hover:text-brand-700 dark:hover:text-brand-300
                                          {{ $subtask->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
                                    {{ $subtask->title }}
                                </a>
                            @endif

                            {{-- 外す: 親子を切るだけ。課題は一覧に戻る --}}
                            <form action="{{ route('subtasks.detach', [$task, $subtask]) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" aria-label="サブタスクから外す" title="サブタスクから外す"
                                        class="rounded p-1 text-slate-400 opacity-0 transition hover:text-slate-700 focus-visible:opacity-100 group-hover/sub:opacity-100 dark:hover:text-white">
                                    <x-icon name="unlink" class="size-3.5" />
                                </button>
                            </form>

                            {{-- 削除: ここで作ったサブタスクだけ。引き込んだ課題は消させない --}}
                            @if ($subtask->issue_type->isSubtask())
                                <form action="{{ route('subtasks.destroy', [$task, $subtask]) }}" method="POST"
                                      data-confirm="サブタスク「{{ $subtask->title }}」を削除します。よろしいですか？">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" aria-label="サブタスクを削除" title="削除"
                                            class="rounded p-1 text-slate-400 opacity-0 transition hover:text-rose-600 focus-visible:opacity-100 group-hover/sub:opacity-100">
                                        <x-icon name="trash" class="size-3.5" />
                                    </button>
                                </form>
                            @endif
                        </li>
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

            {{-- いま自分が誰かの子なら、それを示して親へ行けるようにする --}}
            @if ($task->isChild())
                <p class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    この課題は
                    <a href="{{ route('tasks.show', $task->parent) }}"
                       class="font-mono tracking-wider underline hover:text-brand-700 dark:hover:text-brand-300">
                        {{ $task->parent->key() }}
                    </a>
                    のサブタスクです。
                </p>
            @endif
        </section>

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

    @include('tasks.timeline')
@endsection
