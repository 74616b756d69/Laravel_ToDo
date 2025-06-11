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
    <h2>タスク編集ページ</h2>

    {{-- 【重要】task.updateのルーティングは後で作成する --}}
    <form action={{ route('task.update', ['id' => $task["id"]]) }} method="POST">
        @csrf
        {{-- 【重要】HTTPメソッドをPUTに変更 --}}
        @method('PUT')

        <label>タイトル</label>
        {{-- value属性に$task["title"]を設定することで、編集前のタイトルが入力値に戻る --}}
        <input type="text" name="title" value="{{ $task["title"] }}">

        <label>内容</label>
        {{-- textarea内に$task["content"]を設定することで、編集前の内容が入力値に入る --}}
        <textarea name="content">{{ $task["content"]}}</textarea>

        <button type="submit">更新</button>
    </form>

    {{-- タスク一覧ページに戻るボタン --}}
    <a href="{{ route("task.show", ["id" => $task["id"]]) }}>戻る</a>
</body>
</html>