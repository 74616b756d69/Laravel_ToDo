@props(['name' => 'content', 'value' => null, 'placeholder' => '詳細やメモを入力できます。見出し・箇条書き・チェックリストが使えます。'])

@php
    $groups = [
        [
            ['bold', 'B', '太字', 'font-bold'],
            ['italic', 'I', '斜体', 'italic font-serif'],
            ['strike', 'S', '打ち消し線', 'line-through'],
            ['code', '</>', 'インラインコード', 'font-mono text-[11px]'],
        ],
        [
            ['h2', 'H2', '見出し2', 'font-semibold'],
            ['h3', 'H3', '見出し3', 'font-semibold'],
        ],
        [
            ['bulletList', '•', '箇条書き', 'text-base leading-none'],
            ['orderedList', '1.', '番号付きリスト', ''],
            ['taskList', '☑', 'チェックリスト', 'text-base leading-none'],
        ],
        [
            ['blockquote', '❝', '引用', 'text-base leading-none'],
            ['codeBlock', '{ }', 'コードブロック', 'font-mono text-[11px]'],
            ['horizontalRule', '—', '区切り線', ''],
            ['link', '🔗', 'リンク', 'text-[11px]'],
        ],
        [
            ['undo', '↶', '元に戻す', 'text-base leading-none'],
            ['redo', '↷', 'やり直す', 'text-base leading-none'],
        ],
    ];
@endphp

<div data-editor data-placeholder="{{ $placeholder }}"
     class="overflow-hidden rounded-xl border border-slate-200 bg-white transition focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-900">

    <div class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50/80 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800/60">
        @foreach ($groups as $index => $group)
            @if ($index > 0)
                <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700"></span>
            @endif
            @foreach ($group as [$action, $label, $title, $classes])
                <button type="button" data-editor-action="{{ $action }}" title="{{ $title }}"
                        aria-label="{{ $title }}" aria-pressed="false"
                        class="editor-button {{ $classes }}">{{ $label }}</button>
            @endforeach
        @endforeach
    </div>

    {{-- 実際に送信されるのはこの hidden input。エディタの内容が随時同期される --}}
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-editor-input>

    {{-- JS が無効な環境では通常の textarea として編集できる --}}
    <noscript>
        <textarea name="{{ $name }}" rows="6"
                  class="w-full resize-y bg-transparent px-4 py-3 text-sm focus:outline-none">{{ $value }}</textarea>
    </noscript>

    <div data-editor-content class="max-h-[28rem] overflow-y-auto px-4 py-3"></div>
</div>
