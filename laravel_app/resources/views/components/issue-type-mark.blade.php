@props(['type'])

{{--
    課題の種別。

    アイコンを裸で置くと、隣にある完了チェックボックスと形が似てしまい
    （タスクの印は四角＋チェック）、押せるものに見える。
    面に載せることで「印であって操作ではない」ことを形で示す。

    面は中立色にして、色は記号のほうに持たせる。
    種別ごとに面を染めると、同じ行にあるステータスやタグの色と競合して
    行の中でいちばん強い色が種別になってしまうため。
--}}
<span role="img" title="{{ $type->label() }}" aria-label="課題タイプ: {{ $type->label() }}"
      {{ $attributes->merge(['class' => 'grid size-5 shrink-0 place-items-center rounded-md bg-slate-100 dark:bg-white/10 '.$type->iconClasses()]) }}>
    <x-icon :name="$type->icon()" class="size-3.5" />
</span>
