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
        <h1><a href="{{ route("sign_up") }}">ToDoリスト</a></h1>
    </header>
    <h2 class="sin-uppage_subtitle">新規登録</h2>
    <!-- {{-- 【重要】sign_up.storeのルーティングは後で作成する --}} -->
    <div class="form_margin_div">
        <form id="signupForm" action={{ route('sign_up.store') }} method="POST">
            <!-- {{-- 【重要】sign_up.storeのルーティングは後で作成する --}}
            {{-- (@csrtと書くだけでOK) --}} -->
            @csrf
            <label>名前</label>
            <input type="text" id="name" name="name" placeholder="山田太郎">

            <label>メールアドレス</label>
            <input type="email" id="email" name="email" placeholder="example@mail.com">

            <label>パスワード</label>
            <input type="password" id="password" name="password" placeholder="パスワードを入力">

            <label>パスワード確認</label>
            <input type="password" id="passwordConfirmation" name="passwordConfirmation" placeholder="パスワードを再入力" >

            <button type="submit">サインアップ</button>
        </form>
    </div>
        <p class="login_page_a"><a href="{{ route("login") }}">ログイン画面へ</a></p>
    {{-- エラーがある場合に表示する --}}
    @if ($error)
        <p>{{ $error }}</p>
    @endif
    <script>
        document.getElementById("signupForm").addEventListener('click', function (e){
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirmation = document.getElementById('passwordConfirmation').value;

            if (!name || !email ||!password || !passwordConfirmation) {
                e.preventDefault();
                alert('エラー:すべての項目を入力してください');
                return;
            }
        });
    </script>
</body>
</html>