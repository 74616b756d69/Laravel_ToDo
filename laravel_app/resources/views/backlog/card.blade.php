{{--
    バックログ 1 行分。スプリント間をドラッグで移動する。

    並びは一覧・ボード・サブタスク行と同じ語彙で揃える
    （種別 → キー → 要約 → メタ → 見積り → 担当者 → ステータス）。
--}}
<li data-issue-id="{{ $issue->id }}" data-move-url="{{ route('backlog.move', $issue) }}"
    class="flex cursor-grab flex-wrap items-center gap-x-3 gap-y-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800">
    <x-issue-type-mark :type="$issue->issue_type" />

    <a href="{{ route('tasks.show', $issue) }}"
       class="shrink-0 font-mono text-[11px] tracking-wider text-slate-400 transition hover:text-brand-700 dark:text-slate-500 dark:hover:text-brand-300">
        {{ $issue->key() }}
    </a>

    <a href="{{ route('tasks.show', $issue) }}"
       class="min-w-0 flex-1 basis-40 truncate text-sm font-medium transition hover:text-brand-700 dark:hover:text-brand-300
              {{ $issue->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
        {{ $issue->title }}
    </a>

    @foreach ($issue->tags as $tag)
        <x-tag-mark :tag="$tag" />
    @endforeach

    <x-due-date :issue="$issue" />

    <x-priority-mark :priority="$issue->priority" />

    {{-- 見積りはスプリントに何ポイント積むかを決める材料なので、この画面では必ず出す --}}
    <span title="{{ $issue->story_points === null ? '見積り未設定' : 'ストーリーポイント' }}"
          class="grid size-6 shrink-0 place-items-center rounded-full text-[11px] font-semibold tabular-nums
                 {{ $issue->story_points === null
                        ? 'border border-dashed border-slate-300 text-slate-400 dark:border-slate-600 dark:text-slate-500'
                        : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300' }}">
        {{ $issue->story_points ?? '—' }}
    </span>

    <x-avatar :user="$issue->assignee" />

    <x-badge :classes="$issue->status->badgeClasses()" :dot="$issue->status->dotClasses()">
        {{ $issue->status->name }}
    </x-badge>
</li>
