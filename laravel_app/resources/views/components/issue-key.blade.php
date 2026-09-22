@props(['issue'])

{{--
    課題キーの表示は 1 か所にまとめる。

    プロジェクトキーは一覧の中では全行同じ文字列なので薄く置き、
    行ごとに違う番号だけを読める濃さにする。
    一覧・ボード・バックログ・詳細のどこでも同じ表情にすることで、
    「この形の文字列＝課題キー」と覚えてもらう。
--}}
<span {{ $attributes->merge(['class' => 'issue-key']) }}>
    <span class="issue-key-prefix">{{ $issue->project->key }}-</span><span
        class="issue-key-number">{{ $issue->issue_number }}</span>
</span>
