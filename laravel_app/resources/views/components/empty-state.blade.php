@props(['title', 'description' => null])

<div class="flex flex-col items-center gap-3 px-6 py-16 text-center">
    <span class="grid size-14 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/5 dark:text-slate-500">
        <x-icon name="inbox" class="size-7" />
    </span>
    <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $title }}</p>
    @if ($description)
        <p class="max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
