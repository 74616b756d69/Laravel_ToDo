@php
    /*
     * ヘッダーは 2 段に分ける。
     *
     * 上段は「いま誰が・どのプロジェクトに居るか」と検索、下段は「行き先」。
     * 役割の違うものを 1 行に詰めると、ナビゲーションが検索窓やアカウントに
     * 押されて幅が読めなくなるため、段を分けて画面の横幅を取り合わないようにした。
     *
     * プロジェクト設定・タグ・テーマ・ログアウトは日に何度も押すものではないので
     * 引き続きメニューへ畳み、下段に常時見えるのは 4 つまでにしている。
     */
    $navigation = [
        ['route' => 'tasks.index', 'label' => 'タスク', 'active' => request()->routeIs('tasks.*')],
        ['route' => 'board', 'label' => 'ボード', 'active' => request()->routeIs('board')],
        ['route' => 'backlog', 'label' => 'バックログ', 'active' => request()->routeIs('backlog') || request()->routeIs('sprints.*')],
        ['route' => 'dashboard', 'label' => '分析', 'active' => request()->routeIs('dashboard')],
    ];

    $menuItem = 'flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5';
@endphp

<header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    {{-- 上段：ブランド・プロジェクト・検索・アカウント --}}
    <div @class([
        'mx-auto flex h-14 w-full max-w-5xl items-center gap-2 px-4 sm:gap-4 sm:px-6',
        // 下段があるときだけ薄い区切りを入れる（無いと 2 段が 1 かたまりに見える）
        'border-b border-slate-100 dark:border-white/5' => auth()->check(),
    ])>
        <a href="{{ route(auth()->check() ? 'tasks.index' : 'welcome') }}"
           class="shrink-0 text-[15px] font-bold tracking-tight whitespace-nowrap">
            {{ config('app.name') }}
        </a>

        @auth
            {{--
                プロジェクトの切り替え。
                2 段にして横幅に余裕ができたので、広い画面では名前も並べる。
                狭い画面ではキーだけに畳む（「いまどこに居るか」はキーで足りる）。
            --}}
            <x-menu align="left" width="w-64">
                <x-slot:trigger>
                    <span class="sr-only">プロジェクトを切り替える（現在：{{ $currentProject->name }}）</span>
                    <span aria-hidden="true" class="flex min-w-0 items-center gap-2">
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold tracking-wider text-slate-600 dark:bg-white/5 dark:text-slate-300">
                            {{ $currentProject->key }}
                        </span>
                        <span class="hidden max-w-40 truncate text-sm text-slate-600 sm:block dark:text-slate-300">
                            {{ $currentProject->name }}
                        </span>
                    </span>
                </x-slot:trigger>

                <p class="px-2.5 pt-1.5 pb-1 text-xs text-slate-400 dark:text-slate-500">プロジェクトを切り替える</p>

                @foreach ($availableProjects as $project)
                    {{-- 選択と送信を分けず、1 クリックで切り替わるようにする --}}
                    <form action="{{ route('projects.switch') }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="project" value="{{ $project->id }}">
                        <button type="submit"
                                @class([$menuItem, 'bg-slate-100 font-medium dark:bg-white/5' => $project->id === $currentProject->id])>
                            <span class="w-14 shrink-0 font-mono text-xs tracking-wider text-slate-500 dark:text-slate-400">
                                {{ $project->key }}
                            </span>
                            <span class="truncate">{{ $project->name }}</span>
                        </button>
                    </form>
                @endforeach

                <div class="my-1.5 border-t border-slate-100 dark:border-white/5"></div>

                <a href="{{ route('projects.index') }}" class="{{ $menuItem }}">
                    <x-icon name="folder" class="size-4 text-slate-400" /> プロジェクト一覧
                </a>
                {{-- 設定を触れるのは管理者だけなので、導線もそこに合わせる --}}
                @can('update', $currentProject)
                    <a href="{{ route('projects.edit', $currentProject) }}" class="{{ $menuItem }}">
                        <x-icon name="settings" class="size-4 text-slate-400" /> このプロジェクトの設定
                    </a>
                @endcan
            </x-menu>
        @endauth

        <div class="ml-auto flex shrink-0 items-center gap-1">
            @auth
                {{-- 課題キー（PROJ-123）ならその課題へ直行、それ以外はキーワード検索 --}}
                <form action="{{ route('search') }}" method="GET" class="relative hidden sm:block">
                    <label for="global-search" class="sr-only">課題を検索</label>
                    <x-icon name="search" class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-slate-400" />
                    <input id="global-search" type="search" name="q" value="{{ request()->query('keyword') }}"
                           placeholder="{{ $currentProject->key }}-1 またはキーワード"
                           class="w-48 rounded-lg border border-slate-200 bg-white py-1.5 pr-2 pl-8 text-sm lg:w-64 dark:border-slate-700 dark:bg-slate-900">
                </form>

                {{-- 画面が狭いときは検索窓を畳み、一覧の絞り込みへ送る --}}
                <a href="{{ route('tasks.index') }}" aria-label="課題を検索"
                   class="grid size-8 place-items-center rounded-md text-slate-400 hover:text-slate-900 sm:hidden dark:hover:text-white">
                    <x-icon name="search" class="size-5" />
                </a>

                <x-menu width="w-52">
                    <x-slot:trigger>
                        <span class="sr-only">アカウントメニュー</span>
                        {{-- 名前を並べる代わりに頭文字だけ出す。誰でログインしているかは分かる --}}
                        <span aria-hidden="true"
                              class="grid size-7 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                            {{ mb_substr(auth()->user()->name, 0, 1) }}
                        </span>
                    </x-slot:trigger>

                    <p class="truncate px-2.5 pt-1.5 pb-2 text-sm font-medium">{{ auth()->user()->name }}</p>

                    <div class="mb-1.5 border-t border-slate-100 dark:border-white/5"></div>

                    <a href="{{ route('tags.index') }}" class="{{ $menuItem }}">
                        <x-icon name="tag" class="size-4 text-slate-400" /> タグ管理
                    </a>

                    <x-theme-toggle :class="$menuItem" with-label />

                    <div class="my-1.5 border-t border-slate-100 dark:border-white/5"></div>

                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="{{ $menuItem }}">
                            <x-icon name="logout" class="size-4 text-slate-400" /> ログアウト
                        </button>
                    </form>
                </x-menu>
            @else
                <x-theme-toggle />

                <a href="{{ route('login') }}"
                   class="px-2 py-1 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">ログイン</a>
                <a href="{{ route('register') }}"
                   class="ml-1 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                    新規登録
                </a>
            @endauth
        </div>
    </div>

    @auth
        {{-- 下段：現在地は下線で示す。狭い画面では横スクロールさせる --}}
        <nav class="mx-auto -mb-px flex h-11 w-full max-w-5xl items-stretch gap-4 overflow-x-auto px-4 sm:gap-6 sm:px-6">
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
</header>
