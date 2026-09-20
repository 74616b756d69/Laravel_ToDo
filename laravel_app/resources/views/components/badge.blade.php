@props(['classes' => '', 'dot' => null])

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dot }}"></span>
    @endif
    {{ $slot }}
</span>
