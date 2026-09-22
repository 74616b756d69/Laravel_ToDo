@props(['classes' => '', 'dot' => null])

<span {{ $attributes->merge(['class' => "chip {$classes}"]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dot }}"></span>
    @endif
    {{ $slot }}
</span>
