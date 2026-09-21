@props(['tag'])

{{--
    一覧・カードの中でのタグ。

    タグ管理画面や絞り込みでは色付きの札（x-badge）のままにするが、
    カードの中では点と文字だけにする。1 つの課題に 3 つ付くことがあり、
    札のまま並べるとステータスより広い面積を取ってしまうため。
    色は残すので、絞り込みで覚えた色との対応は崩れない。
--}}
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 text-xs text-slate-500 dark:text-slate-400']) }}>
    <span class="size-1.5 rounded-full {{ $tag->color->swatchClasses() }}"></span>{{ $tag->name }}
</span>
