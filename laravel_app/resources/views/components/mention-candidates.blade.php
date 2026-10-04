@props(['users'])

{{--
    @メンションの候補。ページ内のエディタはすべてここを読む。

    課題画面にはコメントの数だけエディタが並ぶので、エディタごとに持たせずページに 1 つだけ置く。
    候補はその課題のプロジェクトのメンバーだけ（保存時にもサーバーで同じ条件で絞る）。
--}}
<script type="application/json" data-mention-candidates>@json($users->map(fn ($user) => ['id' => $user->id, 'label' => $user->name])->values())</script>
