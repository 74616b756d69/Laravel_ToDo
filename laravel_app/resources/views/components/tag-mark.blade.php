@props(['tag'])

{{--
    一覧・カードの中でのタグ。

    行の中では色を持たせず、灰色の小さな札にする。1 行にステータス・期限切れ・
    優先度（高）という「意味のある色」が並ぶので、分類にすぎないタグまで色を持つと
    どれが注意すべき色なのかが読めなくなる。
    タグの色はタグ管理と絞り込みでだけ使う（選ぶときの手がかりとして）。
--}}
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center rounded-sm bg-slate-100 px-1.5 text-[11px] leading-5 text-slate-600 dark:bg-white/5 dark:text-slate-400']) }}>{{ $tag->name }}</span>
