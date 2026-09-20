@props(['messages'])

@if ($messages)
    <p class="mt-1.5 flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400">
        <x-icon name="alert" class="size-3.5 shrink-0" />
        {{ is_array($messages) ? $messages[0] : $messages }}
    </p>
@endif
