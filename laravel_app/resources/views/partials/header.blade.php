<header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/70 backdrop-blur-md dark:border-white/5 dark:bg-slate-950/70">
    <div class="mx-auto flex h-16 w-full max-w-5xl items-center gap-3 px-4 sm:px-6">
        <a href="{{ route(auth()->check() ? 'tasks.index' : 'welcome') }}"
           class="flex items-center gap-2.5 font-bold tracking-tight">
            <span class="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-sm">
                <x-icon name="check" class="size-5" />
            </span>
            <span class="text-lg">{{ config('app.name') }}</span>
        </a>

        <div class="ml-auto flex items-center gap-2">
            <x-theme-toggle />

            @auth
                <a href="{{ route('tasks.create') }}"
                   class="hidden rounded-xl bg-brand-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700 sm:inline-flex sm:items-center sm:gap-1.5">
                    <x-icon name="plus" class="size-4" /> 新規タスク
                </a>

                <div class="flex items-center gap-2 border-l border-slate-200 pl-2 dark:border-white/10">
                    <span class="hidden text-sm text-slate-600 sm:inline dark:text-slate-300">{{ auth()->user()->name }}</span>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white">
                            ログアウト
                        </button>
                    </form>
                </div>
            @else
                <a href="{{ route('login') }}"
                   class="rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5">ログイン</a>
                <a href="{{ route('register') }}"
                   class="rounded-xl bg-brand-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">新規登録</a>
            @endauth
        </div>
    </div>
</header>
