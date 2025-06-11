<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('/css/style.css') }}">
    <title>ToDoリスト-ログイン</title>
</head>
<body>
    <header> 
        <h1><a href="{{ route("task") }}">ToDoリスト</a></h1>
    </header>
    <h2 class="loginpage_subtitle">ログイン</h2>
    <!-- {{-- 【重要】login.storeのルーティングは後で作成する --}} -->
    <div class="form_margin_div">
        <form action={{ route("login.store") }} method="POST">
            <!-- {{-- フォームのメソッドがPOSTの場合は、csrtトークンを設定する必要がある --}}
            {{-- (@csrfと書くだけでOK) --}} -->
            @csrf
            <label for="email">メールアドレス</label>
            <input type="email" name="email" placeholder="example@mail.com">

            <label for="password">パスワード</label>
            <input type="password" name="password" placeholder="パスワードを入力">

            <button type="submit">ログイン</button>
        </form> 
    </div>
    <p class="sinuppage_a"><a href="{{ route("sign_up") }}">新規登録する</a></p>

    {{-- エラーがある場合に表示する --}}
    @if ($error)
        <p class="loginpage_error_text">{{ $error }}</p>
    @endif
</body>
</html>