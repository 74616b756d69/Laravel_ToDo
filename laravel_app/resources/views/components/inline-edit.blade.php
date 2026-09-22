@props([
    'action',
    // 検証に失敗したときに開き直すため、この編集欄が扱う入力名を渡す（複数可）
    'field' => [],
    'label',
    'method' => 'PATCH',
    // 表示側（summary）に足すクラス。行に並べるか、ブロックとして置くかを呼び出し側で決める
    'triggerClass' => '',
    // 権限が無いときは読むだけ。呼び出し側で @can を書き分けずに済むようここで畳む
    'editable' => true,
])

@php
    // 保存に失敗して戻ってきたときは、入力を抱えたまま開いた状態で見せる。
    // 閉じたままだと、どこで何を直せばいいのか分からない。
    $hasError = collect((array) $field)->contains(fn ($name) => $errors->has($name));
@endphp

{{--
    その場で編集する欄。

    details / summary で作るので JS 無しでも開閉できる。表示（summary）を押すと
    入力に変わり、保存すればその項目だけが 1 リクエストで更新される。
    「編集ボタンから編集画面へ移動して全項目のフォームを見る」という遠回りを避けたい。

    $display に表示、既定スロットに入力欄を入れる。
--}}
@if (! $editable)
    <div {{ $attributes->merge(['class' => 'flex items-center gap-1.5 '.$triggerClass]) }}>{{ $display }}</div>
@else
    <details data-inline-edit {{ $attributes->merge(['class' => 'group/edit']) }} @if ($hasError) open @endif>
        <summary title="クリックして{{ $label }}を編集"
                 class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg transition hover:bg-slate-100 dark:hover:bg-white/5 {{ $triggerClass }}">
            {{ $display }}

            {{-- 押せることの合図。読むときに邪魔にならないよう、重ねたときだけ出す --}}
            <x-icon name="pencil"
                    class="size-3.5 shrink-0 text-slate-400 opacity-0 transition group-hover/edit:opacity-100 group-open/edit:opacity-100" />
            <span class="sr-only">{{ $label }}を編集</span>
        </summary>

        <form action="{{ $action }}" method="POST" data-inline-form class="mt-2 space-y-2">
            @csrf
            @method($method)

            {{ $slot }}

            <div class="flex items-center justify-end gap-1">
                {{-- JS があれば Esc でも閉じられる。無いときは summary をもう一度押す --}}
                <button type="button" data-inline-cancel
                        class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/5">
                    キャンセル
                </button>
                <button type="submit"
                        class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-700">
                    保存
                </button>
            </div>
        </form>
    </details>
@endif
