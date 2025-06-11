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
        <h1>ToDOリスト</h1>
    </header>
    <h2>タスク一覧ページ</h2>
    {{-- 一覧テーブルを作成 --}}
    <table>
        <tr>
            <th>タイトル</th>
            <th>内容</th>
            <th>作成日時</th>
            <th>更新日時</th>
        </tr>

        {{-- コントラーラーから受け取った$tasksをループして表示 --}}
        @foreach ($tasks as $task)

        <tr>
            <td>{{ $task["title"] }}</td>
            <td>{{ $task["content"] }}</td>
            <td>{{ $task["created_at"] }}</td>
            <td>{{ $task["updated_at"] }}</td>
            <td>
                {{-- 詳細ページに遷移するリンクを作成 --}}
                <a href={{ route("task.show", ["id" => $task["id"]]) }}>詳細</a>
            </td>
        </tr>
        @endforeach
    </table>

    {{-- タスク作成ページに遷移するボタン --}}
    <a href="{{ route("task.create") }}">タスク作成</a>
</body>
</html>