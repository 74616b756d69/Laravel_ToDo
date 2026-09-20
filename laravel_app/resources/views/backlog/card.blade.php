{{-- バックログ 1 行分。スプリント間をドラッグで移動する --}}
<li data-issue-id="{{ $issue->id }}" data-move-url="{{ route('backlog.move', $issue) }}"
    class="flex cursor-grab flex-wrap items-center gap-x-3 gap-y-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800">
    <span class="shrink-0 font-mono text-[11px] tracking-wider text-slate-400 dark:text-slate-500">
        {{ $issue->key() }}
    </span>

    <a href="{{ route('tasks.show', $issue) }}"
       class="min-w-0 flex-1 truncate text-sm font-medium hover:text-brand-700 dark:hover:text-brand-300
              {{ $issue->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
        {{ $issue->title }}
    </a>

    @if ($issue->story_points !== null)
        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-slate-100 text-[11px] font-semibold tabular-nums text-slate-600 dark:bg-white/10 dark:text-slate-300"
              title="ストーリーポイント">
            {{ $issue->story_points }}
        </span>
    @endif

    <x-badge :classes="$issue->status->badgeClasses()">{{ $issue->status->name }}</x-badge>

    <x-badge :classes="$issue->priority->badgeClasses()" :dot="$issue->priority->dotClasses()">
        {{ $issue->priority->label() }}
    </x-badge>
</li>
