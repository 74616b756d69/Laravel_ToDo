@props(['parent', 'link', 'issue'])

{{--
    リンクされた作業項目 1 行。
    種別アイコン・キー・要約・ステータス・担当者・優先度を並べる。

    サブタスクの行と似ているが、こちらには完了トグルを置かない。
    関連しているだけの課題を、この画面から勝手に進めるべきではないため。
--}}
<li class="group/link flex flex-wrap items-center gap-x-2.5 gap-y-1.5 px-3 py-2 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
    <x-icon :name="$issue->issue_type->icon()"
            class="size-4 shrink-0 {{ $issue->issue_type->iconClasses() }}"
            :title="$issue->issue_type->label()" />

    <a href="{{ route('tasks.show', $issue) }}"
       class="shrink-0 font-mono text-[11px] tracking-wider text-slate-400 hover:text-brand-700 dark:text-slate-500 dark:hover:text-brand-300">
        {{ $issue->key() }}
    </a>

    <a href="{{ route('tasks.show', $issue) }}"
       class="min-w-0 flex-1 basis-40 truncate text-sm hover:text-brand-700 dark:hover:text-brand-300
              {{ $issue->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
        {{ $issue->title }}
    </a>

    <x-badge :classes="$issue->status->badgeClasses()">{{ $issue->status->name }}</x-badge>

    @if ($issue->assignee)
        <span title="担当: {{ $issue->assignee->name }}"
              class="grid size-5 shrink-0 place-items-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
            {{ mb_substr($issue->assignee->name, 0, 1) }}
        </span>
    @endif

    <x-icon :name="$issue->priority->icon()"
            class="size-4 shrink-0 {{ $issue->priority->iconClasses() }}"
            :title="'優先度'.$issue->priority->label()" />

    <form action="{{ route('links.destroy', [$parent, $link]) }}" method="POST" class="shrink-0">
        @csrf
        @method('DELETE')
        <button type="submit" aria-label="関連を外す" title="関連を外す"
                class="rounded p-1 text-slate-400 opacity-0 transition hover:text-slate-700 focus-visible:opacity-100 group-hover/link:opacity-100 dark:hover:text-white">
            <x-icon name="unlink" class="size-3.5" />
        </button>
    </form>
</li>
