@props(['label', 'value', 'accent' => 'text-slate-900 dark:text-white', 'href' => null, 'active' => false])

<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif
    class="card flex flex-col gap-1 px-4 py-3 transition {{ $href ? 'hover:-translate-y-0.5 hover:shadow-md' : '' }} {{ $active ? 'ring-2 ring-brand-500' : '' }}">
    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</span>
    <span class="text-2xl font-bold tabular-nums {{ $accent }}">{{ $value }}</span>
</{{ $href ? 'a' : 'div' }}>
