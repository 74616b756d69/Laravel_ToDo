@extends('layouts.guest')

@section('title', 'ログイン')
@section('heading', 'おかえりなさい')
@section('lead', 'アカウントにログインしてタスクを管理しましょう。')

@section('content')
    @if (config('demo.enabled'))
        {{-- 採用担当の方などがアカウントを作らずに中身を確認できるようにする --}}
        <div class="mb-5 rounded-xl border border-brand-200 bg-brand-50/70 p-4 dark:border-brand-500/30 dark:bg-brand-500/10">
            <div class="flex items-start gap-2.5">
                <x-icon name="sparkles" class="mt-0.5 size-4 shrink-0 text-brand-700 dark:text-brand-300" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-brand-900 dark:text-brand-100">はじめての方へ</p>
                    <p class="mt-0.5 text-xs text-brand-800/80 dark:text-brand-200/80">
                        タスク100件入りのデモアカウントで、登録せずにすべての機能を試せます。
                    </p>

                    <form action="{{ route('login.demo') }}" method="POST" class="mt-3">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                            デモアカウントでログイン
                        </button>
                    </form>

                    <p class="mt-2 text-[11px] text-brand-800/70 dark:text-brand-200/70">
                        手入力する場合：<code class="font-mono">{{ config('demo.email') }}</code> /
                        <code class="font-mono">{{ config('demo.password') }}</code>
                    </p>
                </div>
            </div>
        </div>

        <div class="mb-5 flex items-center gap-3">
            <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></span>
            <span class="text-xs text-slate-400">または</span>
            <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></span>
        </div>
    @endif

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
