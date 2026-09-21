{{--
    ページ送り。Laravel 既定のビューを差し替える。

    差し替える理由は 2 つ。
     - 既定は英語（Previous / Showing 1 to 10 of 101 results）で、画面の他と揃わない
     - 既定のビューは vendor/ の中にあるので、Tailwind に読ませるには
       app.css の @source で vendor を名指しする必要がある。見た目の素が
       composer の管理下にあるのは壊れやすい（実際、あの 1 行が欠けると
       全クラスが消えて素の箇条書きになる）。resources/views に置けばその心配がない。

    $elements は Laravel が組み立てたページ番号の窓。
    要素は「ページ番号 => URL の配列」か、区切りの文字列（…）のいずれか。
--}}
@php
    $link = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2.5 text-sm transition';
    $inactive = $link.' text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5';
    $disabled = $link.' text-slate-300 dark:text-slate-600';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="ページ送り" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-slate-500 tabular-nums dark:text-slate-400">
            {{ $paginator->total() }} 件中 {{ $paginator->firstItem() }}〜{{ $paginator->lastItem() }} 件
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $disabled }}" aria-disabled="true">
                    <x-icon name="arrow-left" class="size-4" /><span class="ml-1 hidden sm:inline">前へ</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $inactive }}">
                    <x-icon name="arrow-left" class="size-4" /><span class="ml-1 hidden sm:inline">前へ</span>
                </a>
            @endif

            {{-- 番号を全部並べると狭い画面で折り返すので、そこでは現在地だけを出す --}}
            <span class="px-2 text-sm text-slate-500 tabular-nums sm:hidden dark:text-slate-400">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            <span class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="{{ $disabled }}">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="{{ $link }} bg-brand-600 font-medium text-white tabular-nums"
                                      aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $inactive }} tabular-nums"
                                   aria-label="{{ $page }} ページ目">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $inactive }}">
                    <span class="mr-1 hidden sm:inline">次へ</span><x-icon name="arrow-right" class="size-4" />
                </a>
            @else
                <span class="{{ $disabled }}" aria-disabled="true">
                    <span class="mr-1 hidden sm:inline">次へ</span><x-icon name="arrow-right" class="size-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
