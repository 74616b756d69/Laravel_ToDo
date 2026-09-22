@props(['label', 'value', 'accent' => 'text-slate-900 dark:text-white', 'href' => null, 'active' => false])

{{--
    件数の升目。

    大きな数字を並べたカードにしない。この数は「眺める指標」ではなく
    「一覧を絞る入口」なので、押せる升目として最小の高さで置き、
    いま効いている升目だけを左の罫線で示す。
--}}
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif
    class="flex items-baseline gap-2 border-l-2 px-3 py-2 transition
           {{ $href ? 'hover:bg-slate-100 dark:hover:bg-white/5' : '' }}
           {{ $active ? 'border-brand-600 bg-slate-100 dark:border-brand-400 dark:bg-white/5' : 'border-transparent' }}">
    <span class="text-xs {{ $active ? 'font-medium text-slate-700 dark:text-slate-200' : 'text-slate-500 dark:text-slate-400' }}">{{ $label }}</span>
    <span class="font-mono text-sm font-medium tabular-nums {{ $accent }}">{{ $value }}</span>
</{{ $href ? 'a' : 'div' }}>
