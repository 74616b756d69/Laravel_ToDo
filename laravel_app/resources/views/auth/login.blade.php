@extends('layouts.guest')

@section('title', 'ログイン')
@section('heading', 'おかえりなさい')
@section('lead', 'アカウントにログインしてタスクを管理しましょう。')

@section('content')
    <form action="{{ route('login') }}" method="POST" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="field-label">メールアドレス</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="email"
                   value="{{ old('email') }}" placeholder="example@mail.com"
                   class="field @error('email') border-rose-400 @enderror">
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <label for="password" class="field-label">パスワード</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   placeholder="••••••••" class="field @error('password') border-rose-400 @enderror">
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" name="remember" value="1"
                   class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
            ログイン状態を保持する
        </label>

        <button type="submit"
                class="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            ログイン
        </button>
    </form>
@endsection

@section('alternative')
    アカウントをお持ちでない方は
    <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">新規登録</a>
@endsection
