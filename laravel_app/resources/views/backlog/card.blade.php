{{--
    バックログ 1 行分。スプリント間をドラッグで移動する。

    並びは一覧・ボード・サブタスク行と同じ語彙で揃える
    （種別 → キー → 要約 → メタ → 見積り → 担当者 → ステータス）。

    行は箱に入れず、区切り線だけで並べる。スプリントという箱の中に
    さらに枠付きの行を積むと、枠線が二重になって中身より罫線が目立つため。
    右側は固定幅の列にして、期限や見積りがどの行でも同じ位置に来るようにする。
--}}
<li data-issue-id="{{ $issue->id }}" data-move-url="{{ route('backlog.move', $issue) }}"
    class="flex cursor-grab items-center gap-3 bg-white px-4 py-2 transition hover:bg-slate-50 active:cursor-grabbing dark:bg-transparent dark:hover:bg-white/[0.03]">
    <x-issue-type-mark :type="$issue->issue_type" />

    <a href="{{ route('tasks.show', $issue) }}" class="w-16 shrink-0">
        <x-issue-key :issue="$issue" />
    </a>

    <a href="{{ route('tasks.show', $issue) }}"
       class="min-w-0 flex-1 truncate text-sm transition hover:text-brand-700 dark:hover:text-brand-300
              {{ $issue->isCompleted() ? 'text-slate-500 dark:text-slate-400' : '' }}">
        {{ $issue->title }}
    </a>

    {{-- タグは広い画面でだけ。狭い画面では要約の幅を優先する --}}
    <span class="hidden shrink-0 items-center gap-1 lg:flex">
        @foreach ($issue->tags as $tag)
            <x-tag-mark :tag="$tag" />
        @endforeach
    </span>

    <span class="hidden w-24 shrink-0 justify-end sm:flex">
        <x-due-date :issue="$issue" />
    </span>

    <x-priority-mark :priority="$issue->priority" />

    {{-- 見積りはスプリントに何ポイント積むかを決める材料なので、この画面では必ず出す --}}
    <span title="{{ $issue->story_points === null ? '見積り未設定' : 'ストーリーポイント' }}"
          class="grid h-5 min-w-6 shrink-0 place-items-center rounded-full px-1 font-mono text-[11px] tabular-nums
                 {{ $issue->story_points === null
                        ? 'text-slate-300 dark:text-slate-600'
                        : 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200' }}">
        {{ $issue->story_points ?? '—' }}
    </span>

    <x-avatar :user="$issue->assignee" />

    <span class="hidden w-28 shrink-0 sm:flex">
        <x-status-lozenge :status="$issue->status" />
    </span>
</li>
