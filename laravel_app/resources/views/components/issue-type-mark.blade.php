@props(['type'])

{{--
    課題の種別。

    完了チェックボックスは丸にしてあるので、こちらは面を持たない記号だけにする
    （四角い面どうしが隣り合うと、どちらが押せるものか分からなくなる）。

    色はバグだけに持たせる。種別ごとに塗り分けると、同じ行にあるステータスの色と
    競合して、行の中でいちばん強い色が種別になってしまうため。
--}}
<span role="img" title="{{ $type->label() }}" aria-label="課題タイプ: {{ $type->label() }}"
      {{ $attributes->merge(['class' => 'grid size-4 shrink-0 place-items-center '.$type->iconClasses()]) }}>
    <x-icon :name="$type->icon()" class="size-4" />
</span>
