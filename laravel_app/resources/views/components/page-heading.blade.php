@props(['title', 'back' => null, 'backLabel' => '戻る'])

<div class="animate-rise mb-6">
    @if ($back)
        <a href="{{ $back }}"
           class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">
            <x-icon name="arrow-left" class="size-4" /> {{ $backLabel }}
        </a>
    @endif
    <h1 class="text-2xl font-bold tracking-tight">{{ $title }}</h1>
</div>
