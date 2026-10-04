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
     * 人を色で塗り分けない。行の右端にはステータスの色が並ぶので、
     * 担当者まで色を持つと、意味のない色がいちばん目立ってしまう。
     * 誰かは頭文字と title / aria-label の名前で示す。
     */
    $colors = 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200';
@endphp

@if ($user)
    {{-- title はマウス向け、aria-label は読み上げ向け。頭文字だけでは誰か分からないため両方置く --}}
    <span role="img" title="{{ $label }}: {{ $user->name }}" aria-label="{{ $label }}: {{ $user->name }}"
          {{ $attributes->merge(['class' => "grid shrink-0 place-items-center rounded-full font-semibold {$box} {$colors}"]) }}>
        {{ mb_substr($user->name, 0, 1) }}
    </span>
@else
    {{-- 未割り当ても「欄が空」ではなく形として出す。埋まっていないことに気づけるように --}}
    <span role="img" title="未割り当て" aria-label="未割り当て"
          {{ $attributes->merge(['class' => "grid shrink-0 place-items-center rounded-full border border-dashed border-slate-300 text-slate-400 {$box} dark:border-slate-600 dark:text-slate-500"]) }}>
        <x-icon name="user" class="size-3" />
    </span>
@endif
