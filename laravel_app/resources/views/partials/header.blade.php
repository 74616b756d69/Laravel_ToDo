@php
    $navigation = [
        ['route' => 'projects.index', 'label' => 'プロジェクト', 'active' => request()->routeIs('projects.*')],
        ['route' => 'tasks.index', 'label' => 'タスク', 'active' => request()->routeIs('tasks.*')],
        ['route' => 'backlog', 'label' => 'バックログ', 'active' => request()->routeIs('backlog') || request()->routeIs('sprints.*')],
        ['route' => 'board', 'label' => 'ボード', 'active' => request()->routeIs('board')],
        ['route' => 'dashboard', 'label' => '分析', 'active' => request()->routeIs('dashboard')],
        ['route' => 'tags.index', 'label' => 'タグ', 'active' => request()->routeIs('tags.*')],
    ];
@endphp

<header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="mx-auto flex h-14 w-full max-w-5xl items-center gap-6 px-4 sm:px-6">
        <a href="{{ route(auth()->check() ? 'tasks.index' : 'welcome') }}"
           class="text-[15px] font-bold tracking-tight whitespace-nowrap">
            {{ config('app.name') }}
        </a>

        @auth
            {{-- 現在地は下線で示す。狭い画面では横スクロールさせる --}}
            <nav class="-mb-px flex h-full min-w-0 flex-1 items-stretch gap-5 overflow-x-auto">
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'flex shrink-0 items-center border-b-2 text-sm whitespace-nowrap',
                           'border-brand-600 font-medium text-slate-900 dark:border-brand-400 dark:text-white' => $item['active'],
                           'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' => ! $item['active'],
                       ])>{{ $item['label'] }}</a>
                @endforeach
            </nav>
        @endauth

        <div class="ml-auto flex shrink-0 items-center gap-1">
            <x-theme-toggle />

            @auth
                <span class="ml-2 hidden text-sm text-slate-500 sm:inline dark:text-slate-400">
                    {{ auth()->user()->name }}
                </span>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="px-2 py-1 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                        ログアウト
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}"
                   class="px-2 py-1 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">ログイン</a>
                <a href="{{ route('register') }}"
                   class="ml-1 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                    新規登録
                </a>
            @endauth
        </div>
    </div>
</header>
