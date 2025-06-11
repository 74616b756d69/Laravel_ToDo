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
    <h2>ログインページ</h2>
    <!-- {{-- 【重要】login.storeのルーティングは後で作成する --}} -->
    <form action={{ route("login.store") }} method="POST">
        <!-- {{-- フォームのメソッドがPOSTの場合は、csrtトークンを設定する必要がある --}}
        {{-- (@csrfと書くだけでOK) --}} -->
        @csrf
        <label for="email">メールアドレス</label>
        <input type="email" name="email">

        <label for="password">パスワード</label>
        <input type="password" name="password">

        <button type="submit">ログイン</button>
    </form> 

    {{-- エラーがある場合に表示する --}}
    @if ($error)
        <p>{{ $error }}</p>
    @endif
</body>
</html>