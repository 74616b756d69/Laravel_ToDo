@props([
    'action',
    // 検証に失敗したときに開き直すため、この編集欄が扱う入力名を渡す（複数可）
    'field' => [],
    // 同じ入力名の欄が 1 画面に並ぶとき（コメントなど）のエラーバッグ名
    'bag' => null,
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
    $bagErrors = $bag === null ? $errors : $errors->getBag($bag);
    $hasError = collect((array) $field)->contains(fn ($name) => $bagErrors->has($name));
@endphp

{{--
    その場で編集する欄。

    details / summary で作るので JS 無しでも開閉できる。表示（summary）を
    ダブルクリックすると入力に変わり、入力から離れるとその項目だけが
    1 リクエストで更新される。「編集ボタンから編集画面へ移動して全項目のフォームを
    見る」という遠回りを避けたいので、編集ボタンも保存ボタンも置かない。

    1 クリックで開かないのは、表示の中の文字を選んだりリンクを押したりする方が
    多いから。開く操作はダブルクリックに寄せて、読むときの邪魔をしない。

    $display に表示、既定スロットに入力欄を入れる。
--}}
@if (! $editable)
    <div {{ $attributes->merge(['class' => 'flex items-center gap-1.5 '.$triggerClass]) }}>{{ $display }}</div>
@else
    <details data-inline-edit {{ $attributes->merge(['class' => 'group/edit']) }} @if ($hasError) open @endif>
        {{--
            マウスはダブルクリック（app.js が 1 クリック目の既定動作を止める）。
            キーボードの Enter / Space はそのまま開く。JS が無い環境では
            1 クリックで開く素の details として動く。
        --}}
        <summary title="ダブルクリックして{{ $label }}を編集"
                 class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg transition hover:bg-slate-100 dark:hover:bg-white/5 {{ $triggerClass }}">
            {{ $display }}
            <span class="sr-only">{{ $label }}を編集</span>
        </summary>

        <form action="{{ $action }}" method="POST" data-inline-form class="mt-2 space-y-2">
            @csrf
            @method($method)

            {{ $slot }}

            {{--
                保存ボタンは置かない。入力から離れた時点で app.js が送信する。
                押す場所を探さずに次の項目へ進めるようにしたい。

                JS が無ければ離れても何も起きないので、そのときだけ出す。
            --}}
            <noscript>
                <div class="flex items-center justify-end">
                    <button type="submit"
                            class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-700">
                        保存
                    </button>
                </div>
            </noscript>
        </form>
    </details>
@endif
