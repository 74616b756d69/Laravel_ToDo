<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    <h1>タスクの詳細ページ</h1>

    {{-- コントラーラーから受け取った$taskを表示 --}}
    <p>タイトル: {{ $task["title"] }}</p>
    <p>内容: {{ $task["content"] }}</p>
    <p>作成日時: {{ $task["created_at"] }}</p>
    <p>更新日時: {{ $task["updated_at"] }}</p>

    {{-- タスク一覧ページに戻るボタン --}}
    <a href="{{ route("task") }}">戻る</a>
    {{-- 編集ページに遷移するリンクを追加 --}}
    <a href="{{ route("task.edit", ["id" => $task["id"]]) }}">編集</a>

    {{-- 削除処理を行うフォームを作成 --}}
    <form action={{ route('task.destroy', ['id' => $task["id"]]) }} method="POST">
        @csrf
        {{-- 【重要】HTTPメソッドをDELETEに変更 --}}
        @method('DELETE')
        <button type="submit">消去する</button>
    </form>
</body>
</html>