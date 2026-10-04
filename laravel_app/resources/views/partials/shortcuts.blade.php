{{--
    キーボードショートカットの一覧（? で開く）。
    動きは resources/js/features/shortcuts.js。ここに書いたものと、そこで受けるキーを揃えること。
--}}
@php
    $groups = [
        'どの画面でも' => [
            ['?', 'この一覧を開く'],
            ['/', '検索窓へ移動'],
            ['c', '課題を作成'],
            ['g → i', '課題一覧へ'],
            ['g → b', 'ボードへ'],
            ['g → l', 'バックログへ'],
            ['g → d', '分析へ'],
            ['g → n', '通知へ'],
        ],
        '課題の画面' => [
            ['w', 'ウォッチする / 外す'],
            ['m', 'コメントを書く'],
            ['Ctrl / ⌘ + Enter', '編集中の欄を保存'],
            ['Esc', '編集をやめる'],
        ],
    ];
@endphp

{{-- c で開く先。画面に作成ボタンが無いページもあるので、ここに置いておく --}}
<a href="{{ route('tasks.create') }}" data-shortcut-target="create" hidden></a>

<dialog data-shortcuts-dialog aria-labelledby="shortcuts-title"
        class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-0 text-slate-800 shadow-xl backdrop:bg-slate-950/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-white/5">
        <h2 id="shortcuts-title" class="text-sm font-semibold">キーボードショートカット</h2>
        <form method="dialog">
            <button type="submit" aria-label="閉じる" class="grid size-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white">
                <x-icon name="close" class="size-4" />
            </button>
        </form>
    </div>
    <div class="space-y-4 px-5 py-4">
        @foreach ($groups as $title => $rows)
            <section>
                <h3 class="mb-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $title }}</h3>
                <dl class="space-y-1 text-sm">
                    @foreach ($rows as [$keys, $label])
                        <div class="flex items-center justify-between gap-4">
                            <dt>{{ $label }}</dt>
                            <dd class="flex gap-1">
                                @foreach (explode(' → ', $keys) as $index => $key)
                                    @if ($index > 0)<span class="text-xs text-slate-400">→</span>@endif
                                    <kbd class="rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-800">{{ $key }}</kbd>
                                @endforeach
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
</dialog>
