@props(['label', 'value', 'accent' => 'text-slate-900 dark:text-white', 'href' => null, 'active' => false])

{{--
    件数つきの切り替えタブ。

    大きな数字を並べたカードにしない。この数は「眺める指標」ではなく
    「一覧を絞る入口」なので、検索欄と同じ行に収まるセグメントとして置き、
    いま効いているものだけを面で示す。
--}}
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif
    @if ($active) aria-current="page" @endif
    class="inline-flex shrink-0 items-baseline gap-1.5 rounded-sm px-2.5 py-1 transition
           {{ $active
                ? 'bg-white shadow-xs dark:bg-white/10'
                : ($href ? 'hover:bg-white/60 dark:hover:bg-white/5' : '') }}">
    <span class="text-xs {{ $active ? 'font-medium text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400' }}">{{ $label }}</span>
    <span class="font-mono text-xs font-medium tabular-nums {{ $accent }}">{{ $value }}</span>
</{{ $href ? 'a' : 'div' }}>
