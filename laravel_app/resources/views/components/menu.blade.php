@props(['align' => 'right', 'width' => 'w-56'])

{{--
    ヘッダーのドロップダウン。

    details / summary で作るので、JS が無くても開閉できる（バックログの
    スプリント作成と同じ作り）。JS は「外側をクリックしたら閉じる」ぶんだけの増補。

    $trigger に見出し、既定スロットに中身を入れる。
--}}
<details {{ $attributes->merge(['class' => 'relative shrink-0']) }} data-menu>
    <summary class="flex cursor-pointer list-none items-center gap-1 rounded-lg px-2 py-1.5 text-sm text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5">
        {{ $trigger }}
        <x-icon name="chevron-down" class="size-3.5 shrink-0 text-slate-400" />
    </summary>

    <div @class([
        'card absolute z-20 mt-2 p-1.5 shadow-lg',
        $width,
        'right-0' => $align === 'right',
        'left-0' => $align === 'left',
    ])>
        {{ $slot }}
    </div>
</details>
