<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500|noto-sans-jp:400,500,700" rel="stylesheet">
    <script>
        (() => {
            const saved = localStorage.getItem('theme');
            const dark = saved ? saved === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-8 flex flex-col items-center gap-3 text-center">
            <a href="{{ route('welcome') }}" class="grid size-11 place-items-center rounded-lg bg-brand-600 text-white">
                <x-icon name="check" class="size-6" />
            </a>
            <h1 class="text-xl font-bold tracking-tight">@yield('heading')</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">@yield('lead')</p>
        </div>

        <div class="card p-6 sm:p-7">
            <x-flash />
            @yield('content')
        </div>

        <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">@yield('alternative')</p>
    </div>
</body>
</html>
