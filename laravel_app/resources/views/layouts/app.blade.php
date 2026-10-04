<!DOCTYPE html>
<html lang="ja" class="scroll-pt-20">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '課題') | {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    {{-- swap: フォントの読み込みを待たずに代替書体で描く（待つと読み込み中に日本語が消える） --}}
    <link href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500|noto-sans-jp:400,500,700&display=swap" rel="stylesheet">

    {{-- 描画前にテーマを当てて、ダークモード時のちらつきを防ぐ --}}
    <script>
        (() => {
            const saved = localStorage.getItem('theme');
            const dark = saved ? saved === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')

    {{--
        リアルタイム更新（Reverb）の接続先。ビルド時の環境変数に焼き込まず、ここで渡す
        （同じビルドを、接続先の違う環境で使い回せるように）。Reverb を使わない環境では出さない。
    --}}
    @auth
        @if (config('broadcasting.default') === 'reverb')
            <meta name="realtime" content="{{ json_encode([
                'key' => config('broadcasting.connections.reverb.key'),
                ...config('broadcasting.connections.reverb.client'),
                'user' => auth()->id(),
            ]) }}">
        @endif
    @endauth
</head>
<body class="min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:m-3 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:ring-2 focus:ring-brand-500">
        本文へスキップ
    </a>

    @include('partials.header')

    <main id="main" class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6">
        <x-flash />
        @yield('content')
    </main>

    <footer class="border-t border-slate-200/70 py-6 text-center text-xs text-slate-400 dark:border-white/5 dark:text-slate-500">
        {{ config('app.name') }} — Laravel {{ Illuminate\Foundation\Application::VERSION }}
    </footer>

    @auth
        @include('partials.shortcuts')
    @endauth
</body>
</html>
