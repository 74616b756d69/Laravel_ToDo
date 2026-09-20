<!DOCTYPE html>
<html lang="ja" class="scroll-pt-20">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'タスク') | {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans-jp:400,500,700" rel="stylesheet">

    {{-- 描画前にテーマを当てて、ダークモード時のちらつきを防ぐ --}}
    <script>
        (() => {
            const saved = localStorage.getItem('theme');
            const dark = saved ? saved === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:m-3 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:ring-2 focus:ring-brand-500">
        本文へスキップ
    </a>

    @include('partials.header')

    <main id="main" class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
        <x-flash />
        @yield('content')
    </main>

    <footer class="border-t border-slate-200/70 py-6 text-center text-xs text-slate-400 dark:border-white/5 dark:text-slate-500">
        {{ config('app.name') }} — Laravel {{ Illuminate\Foundation\Application::VERSION }}
    </footer>
</body>
</html>
