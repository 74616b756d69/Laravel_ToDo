@props(['user' => null, 'size' => 'sm', 'label' => '担当'])

{{--
    担当者・起票者を表す丸。名前を並べると行が長くなりすぎるので頭文字だけにする。

    一覧・ボード・バックログ・サブタスクの「誰の作業か」は、すべてこれで表す。
    同じ位置に同じ形のものが出ることが、走り読みできることの条件なので、
    各画面で作り分けずにここへ集約する。
--}}
@php
    // 行の中（sm）とサイドバー（md）の 2 段階だけ。中間を作ると揃わなくなる
    $box = match ($size) {
        'md' => 'size-7 text-xs',
        default => 'size-5 text-[10px]',
    };

    /*
     * 同じ人はいつも同じ色で出す。頭文字だけだと同じ文字で始まる人を見分けられないため、
     * 色を 2 つめの手がかりにする。ID から選ぶので、名前を変えても色は動かない。
     */
    $palette = [
        'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-200',
        'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-200',
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
        'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
        'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
        'bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-200',
    ];
@endphp

@if ($user)
    {{-- title はマウス向け、aria-label は読み上げ向け。頭文字だけでは誰か分からないため両方置く --}}
    <span role="img" title="{{ $label }}: {{ $user->name }}" aria-label="{{ $label }}: {{ $user->name }}"
          {{ $attributes->merge(['class' => "grid shrink-0 place-items-center rounded-full font-semibold {$box} ".$palette[$user->id % count($palette)]]) }}>
        {{ mb_substr($user->name, 0, 1) }}
    </span>
@else
    {{-- 未割り当ても「欄が空」ではなく形として出す。埋まっていないことに気づけるように --}}
    <span role="img" title="未割り当て" aria-label="未割り当て"
          {{ $attributes->merge(['class' => "grid shrink-0 place-items-center rounded-full border border-dashed border-slate-300 text-slate-400 {$box} dark:border-slate-600 dark:text-slate-500"]) }}>
        <x-icon name="user" class="size-3" />
    </span>
@endif
