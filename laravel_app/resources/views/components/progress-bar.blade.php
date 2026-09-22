@props(['done', 'total', 'compact' => false])

@php $percent = $total > 0 ? (int) round($done / $total * 100) : 0; @endphp

<div class="{{ $compact ? 'flex items-center gap-2' : 'space-y-1.5' }}">
    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 {{ $compact ? 'order-2 shrink-0' : '' }}">
        @unless ($compact)
            <span>サブタスク {{ $done }}/{{ $total }}</span>
        @else
            <span class="font-mono tabular-nums">{{ $done }}/{{ $total }}</span>
        @endunless
        @unless ($compact)
            <span class="font-mono font-medium tabular-nums">{{ $percent }}%</span>
        @endunless
    </div>

    <div class="h-1 w-full overflow-hidden rounded-xs bg-slate-200 dark:bg-slate-700"
         role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
         aria-label="サブタスクの進捗">
        <div class="h-full bg-brand-600 transition-[width] duration-300 dark:bg-brand-400" style="width: {{ $percent }}%"></div>
    </div>
</div>
