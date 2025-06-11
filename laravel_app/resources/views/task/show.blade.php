<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ asset('/css/style.css') }}">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    <header> 
        <h1><a href="{{ route("task") }}">ToDoリスト</a></h1>
    </header>
    <h2 class="loginpage_subtitle">タスク詳細</h2>

    {{-- コントラーラーから受け取った$taskを表示 --}}
    <div class="task_text_div">
        <p>タイトル: {{ $task["title"] }}</p>
        <p>内容: {{ $task["content"] }}</p>
        <p>作成日時: {{ $task["created_at"] }}</p>
        <p>更新日時: {{ $task["updated_at"] }}</p>
    </div>

    {{-- タスク一覧ページに戻るボタン --}}
    <div class="page_button">
        <a class="detail_jump" href="{{ route("task") }}">戻る</a>
        {{-- 編集ページに遷移するリンクを追加 --}}
        <a class="detail_jump" href="{{ route("task.edit", ["id" => $task["id"]]) }}">編集</a>
    </div>

    {{-- 削除処理を行うフォームを作成 --}}
    <form class="erase_form" action={{ route('task.destroy', ['id' => $task["id"]]) }} method="POST">
        @csrf
        {{-- 【重要】HTTPメソッドをDELETEに変更 --}}
        @method('DELETE')
        <div class="erase_button">
            <button type="submit">消去する</button>
        </div>
    </form>
</body>
</html>