@extends('layouts.guest')

@section('title', '新規登録')
@section('heading', 'アカウントを作成')
@section('lead', '30秒で登録完了。すぐにタスク管理を始められます。')

@section('content')
    <form action="{{ route('register') }}" method="POST" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="field-label">名前</label>
            <input id="name" name="name" type="text" required autofocus autocomplete="name"
                   value="{{ old('name') }}" placeholder="山田 太郎"
                   class="field @error('name') border-rose-400 @enderror">
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="email" class="field-label">メールアドレス</label>
            <input id="email" name="email" type="email" required autocomplete="email"
                   value="{{ old('email') }}" placeholder="example@mail.com"
                   class="field @error('email') border-rose-400 @enderror">
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <label for="password" class="field-label">パスワード</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   placeholder="英字と数字を含む8文字以上"
                   class="field @error('password') border-rose-400 @enderror">
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <label for="password_confirmation" class="field-label">パスワード（確認）</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password" placeholder="もう一度入力" class="field">
        </div>

        <button type="submit"
                class="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            登録する
        </button>
    </form>
@endsection

@section('alternative')
    すでにアカウントをお持ちの方は
    <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">ログイン</a>
@endsection
