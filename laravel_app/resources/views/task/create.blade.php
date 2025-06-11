<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('/css/style.css') }}">
    <title>ToDoリスト-タスク作成</title>
</head>
<body>
    <header> 
        <h1><a href="{{ route("task") }}">ToDoリスト</a></h1>
    </header>
    <h2 class="sin-uppage_subtitle">タスク作成</h2>
    {{-- 【重要】task.storeのルーティングは後で作成する --}}
    <div class="form_margin_div">
        <form action={{ route('task.store') }} method="POST">
            @csrf
            <label>タイトル</label>
            <input type="text" name="title">

            <label>内容</label>
            <textarea name="content"></textarea>

            <button type="submit">追加</button>
        </form>
    </div>

    {{-- タスク一覧ページに戻るボタン --}}
    <div class="list_jump">
        <a class="detail_jump" href="{{ route("task") }}">戻る</a>
    </div>
</body>
</html>