@props(['withLabel' => false])

{{--
    テーマ切り替え。アイコンだけの丸ボタンと、メニューの中に置く行の 2 通りで使う。
    行として使うときはラベルを添える（メニューの中ではアイコンだけだと意味が読み取れない）。
--}}
<button type="button" data-theme-toggle
        @if (! $withLabel) aria-label="テーマを切り替える" @endif
        {{ $attributes->merge(['class' => $withLabel ? '' : 'grid size-8 place-items-center rounded-md text-slate-400 hover:text-slate-900 dark:hover:text-white']) }}>
    <x-icon name="sun" :class="($withLabel ? 'size-4 text-slate-400' : 'size-5').' shrink-0 dark:hidden'" />
    <x-icon name="moon" :class="($withLabel ? 'size-4 text-slate-400' : 'size-5').' hidden shrink-0 dark:block'" />

    @if ($withLabel)
        {{-- 押したあとの状態ではなく、いま切り替わる先を書く --}}
        <span class="dark:hidden">ダークモードにする</span>
        <span class="hidden dark:inline">ライトモードにする</span>
    @endif
</button>
