@props(['priority', 'highOnly' => false])

{{--
    優先度。バッジではなく記号で出す。

    優先度は 100 件すべてに必ず付いている値なので、バッジにすると
    「優先度低」という読む必要のない札が全行に並び、本当に見てほしい
    ステータスや期限と同じ重さで競合する。形（上向き / 横棒 / 下向き）と
    色だけにして、高いものだけが目に入るようにする。

    一覧・ボード・バックログでは highOnly で「高」以外を消す。
    中・低の記号が全行に並んでも拾うべき情報は無く、ノイズにしかならない。
    消した場合も同じ幅の空きを残し、列の位置は揃えたままにする。
--}}
@if ($highOnly && $priority !== \App\Enums\TaskPriority::High)
    <span aria-hidden="true" {{ $attributes->merge(['class' => 'inline-flex size-4 shrink-0']) }}></span>
@else
    <span role="img" title="優先度{{ $priority->label() }}" aria-label="優先度{{ $priority->label() }}"
          {{ $attributes->merge(['class' => 'inline-flex shrink-0']) }}>
        <x-icon :name="$priority->icon()" class="size-4 {{ $priority->iconClasses() }}" />
    </span>
@endif
