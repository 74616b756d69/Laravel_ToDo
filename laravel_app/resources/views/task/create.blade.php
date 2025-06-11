<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('/css/style.css') }}">
    <title>Document</title>
</head>
<body>
    <header> 
        <h1>ToDOリスト</h1>
    </header>
    <h2>タスク作成ページ</h2>
    {{-- 【重要】task.storeのルーティングは後で作成する --}}
    <form action={{ route('task.store') }} method="POST">
        @csrf
        <label>タイトル</label>
        <input type="text" name="title">

        <label>内容</label>
        <textarea name="content"></textarea>

        <button type="submit">追加</button>
    </form>

    {{-- タスク一覧ページに戻るボタン --}}
    <a href="{{ route("task") }}">戻る</a>
</body>
</html>