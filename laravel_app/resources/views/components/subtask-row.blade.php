@props(['parent', 'subtask'])

{{--
    サブタスク 1 行。Jira のサブタスク欄と同じく、
    種別・キー・要約・ステータス・担当者・優先度・見積りを 1 行に並べる。

    ここで作った軽いサブタスクも、引き込んだ既存の課題も同じ形で出す。
    どちらも独立した課題であることに変わりはないため。
--}}
<li class="group/sub flex flex-wrap items-center gap-x-2.5 gap-y-1.5 rounded-lg px-2 py-2 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
    {{-- 完了トグル。JS なしでも動く --}}
    <form action="{{ route('subtasks.toggle', [$parent, $subtask]) }}" method="POST" class="flex">
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

    <x-icon :name="$subtask->issue_type->icon()"
            class="size-4 shrink-0 {{ $subtask->issue_type->iconClasses() }}"
            :title="$subtask->issue_type->label()" />

    <a href="{{ route('tasks.show', $subtask) }}"
       class="shrink-0 font-mono text-[11px] tracking-wider text-slate-400 hover:text-brand-700 dark:text-slate-500 dark:hover:text-brand-300">
        {{ $subtask->key() }}
    </a>

    <a href="{{ route('tasks.show', $subtask) }}"
       class="min-w-0 flex-1 basis-40 truncate text-sm hover:text-brand-700 dark:hover:text-brand-300
              {{ $subtask->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
        {{ $subtask->title }}
    </a>

    <x-badge :classes="$subtask->status->badgeClasses()">{{ $subtask->status->name }}</x-badge>

    {{-- 担当者は頭文字だけ。名前を並べると行が長くなりすぎる --}}
    @if ($subtask->assignee)
        <span title="担当: {{ $subtask->assignee->name }}"
              class="grid size-5 shrink-0 place-items-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
            {{ mb_substr($subtask->assignee->name, 0, 1) }}
        </span>
    @endif

    <span title="優先度{{ $subtask->priority->label() }}"
          class="size-1.5 shrink-0 rounded-full {{ $subtask->priority->dotClasses() }}"></span>

    @if ($subtask->story_points !== null)
        <span title="ストーリーポイント"
              class="grid size-5 shrink-0 place-items-center rounded-full bg-slate-100 text-[10px] font-semibold tabular-nums text-slate-600 dark:bg-white/10 dark:text-slate-300">
            {{ $subtask->story_points }}
        </span>
    @endif

    <span class="flex shrink-0 items-center gap-0.5">
        {{-- 外す: 親子を切るだけ。課題は一覧に戻る --}}
        <form action="{{ route('subtasks.detach', [$parent, $subtask]) }}" method="POST">
            @csrf
            @method('PATCH')
            <button type="submit" aria-label="サブタスクから外す" title="サブタスクから外す"
                    class="rounded p-1 text-slate-400 opacity-0 transition hover:text-slate-700 focus-visible:opacity-100 group-hover/sub:opacity-100 dark:hover:text-white">
                <x-icon name="unlink" class="size-3.5" />
            </button>
        </form>

        {{-- 削除: ここで作ったサブタスクだけ。引き込んだ課題は消させない --}}
        @if ($subtask->issue_type->isSubtask())
            <form action="{{ route('subtasks.destroy', [$parent, $subtask]) }}" method="POST"
                  data-confirm="サブタスク「{{ $subtask->title }}」を削除します。よろしいですか？">
                @csrf
                @method('DELETE')
                <button type="submit" aria-label="サブタスクを削除" title="削除"
                        class="rounded p-1 text-slate-400 opacity-0 transition hover:text-rose-600 focus-visible:opacity-100 group-hover/sub:opacity-100">
                    <x-icon name="trash" class="size-3.5" />
                </button>
            </form>
        @endif
    </span>
</li>
