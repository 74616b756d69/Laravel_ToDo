{{-- 履歴は不変。操作ボタンは一切付けない --}}
<li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 px-4 py-2 text-sm">
    <x-badge :classes="$activity->field->badgeClasses()">{{ $activity->field->label() }}</x-badge>

    <span class="font-medium">{{ $activity->actorName() }}</span>
    <span class="text-slate-600 dark:text-slate-300">が{{ $activity->describe() }}</span>

    <time datetime="{{ $activity->created_at->toIso8601String() }}"
          class="ml-auto text-xs text-slate-400 dark:text-slate-500"
          title="{{ $activity->created_at->isoFormat('YYYY年M月D日(ddd) HH:mm') }}">
        {{ $activity->created_at->diffForHumans() }}
    </time>
</li>
