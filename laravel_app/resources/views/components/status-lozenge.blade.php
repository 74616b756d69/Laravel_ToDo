@props(['status'])

{{--
    ステータスの表示（Jira のロゼンジ）。

    ステータスはどの画面でもこれで出す。塗りつぶしの小さな札に大文字で名前を置き、
    色はカテゴリの 3 色だけ。行の中で「状態」を示す色はこれ 1 つにして、
    ほかの要素（タグ・担当者・種別）には色を持たせない。
--}}
<span {{ $attributes->merge(['class' => 'lozenge '.$status->lozengeClasses()]) }}>{{ $status->name }}</span>
