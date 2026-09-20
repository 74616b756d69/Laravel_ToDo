@props(['task'])

<li class="group flex items-start gap-3 px-4 py-3.5 transition hover:bg-slate-50/80 dark:hover:bg-white/[0.03]">
    {{-- JS なしでも動く完了トグル。フォーム送信でサーバー側の状態を切り替える --}}
    <form action="{{ route('tasks.completion', $task) }}" method="POST" class="pt-0.5">
        @csrf
        @method('PATCH')
        <button type="submit"
                aria-label="{{ $task->isCompleted() ? '未着手に戻す' : '完了にする' }}"
                class="grid size-5 place-items-center rounded-md border transition
                       {{ $task->isCompleted()
                            ? 'border-emerald-500 bg-emerald-500 text-white'
                            : 'border-slate-300 text-transparent hover:border-brand-500 hover:text-brand-300 dark:border-slate-600' }}">
            <x-icon name="check" class="size-3.5" stroke-width="3" />
        </button>
    </form>

    <div class="min-w-0 flex-1">
        <a href="{{ route('tasks.show', $task) }}"
           class="block truncate font-medium transition group-hover:text-brand-700 dark:group-hover:text-brand-300
                  {{ $task->isCompleted() ? 'text-slate-400 line-through dark:text-slate-500' : '' }}">
            {{ $task->title }}
        </a>

        @if ($task->content)
            <p class="mt-0.5 truncate text-sm text-slate-500 dark:text-slate-400">{{ $task->content }}</p>
        @endif

        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <x-badge :classes="$task->status->badgeClasses()">{{ $task->status->label() }}</x-badge>
            <x-badge :classes="$task->priority->badgeClasses()" :dot="$task->priority->dotClasses()">
                優先度{{ $task->priority->label() }}
            </x-badge>

            @if ($task->due_date)
                <x-badge :classes="$task->isOverdue()
                        ? 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30'
                        : ($task->isDueSoon()
                            ? 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30'
                            : 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700')">
                    <x-icon name="{{ $task->isOverdue() ? 'alert' : 'calendar' }}" class="size-3.5" />
                    {{ $task->due_date->format('n/j') }}
                    @if ($task->isOverdue()) 期限切れ @endif
                </x-badge>
            @endif
        </div>
    </div>

    <a href="{{ route('tasks.edit', $task) }}"
       aria-label="編集"
       class="rounded-lg p-2 text-slate-400 opacity-0 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:opacity-100 group-hover:opacity-100 dark:hover:bg-white/5 dark:hover:text-white">
        <x-icon name="pencil" class="size-4" />
    </a>
</li>
