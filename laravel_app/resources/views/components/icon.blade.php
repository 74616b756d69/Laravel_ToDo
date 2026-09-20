@props(['name'])

{{-- アイコンは外部ライブラリを足さず、必要な分だけ SVG のパスを持つ --}}
@php
    $paths = [
        'check' => '<path d="m5 13 4 4L19 7"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
        'flag' => '<path d="M5 21V4h13l-2.5 4L18 12H5"/>',
        'pencil' => '<path d="M4 20h4l10-10-4-4L4 16v4Z"/><path d="m14.5 5.5 4 4"/>',
        'trash' => '<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/>',
        'arrow-left' => '<path d="M19 12H5m6-7-7 7 7 7"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/>',
        'inbox' => '<path d="M4 13h4l2 3h4l2-3h4"/><path d="M4 13 6 5h12l2 8v6H4v-6Z"/>',
        'alert' => '<path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'sparkles' => '<path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/>',
        // 課題の種別。Jira と同じく、種別は色だけでなく形でも見分けられるようにする
        'epic' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'story' => '<path d="M6 3h12v18l-6-4-6 4V3Z"/>',
        'task' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="m8 12 3 3 5-6"/>',
        'bug' => '<path d="M8 6a4 4 0 0 1 8 0"/><rect x="6" y="8" width="12" height="10" rx="5"/>'
            .'<path d="M3 12h3M18 12h3M4.6 7.4 7 9M19.4 7.4 17 9M4.6 17.6 7 16M19.4 17.6 17 16"/>',
        'subtask' => '<rect x="3" y="4" width="8" height="6" rx="1.5"/>'
            .'<path d="M7 10v5a2 2 0 0 0 2 2h4"/><rect x="13" y="14" width="8" height="6" rx="1.5"/>',
        // 優先度の記号。Jira と同じく、上向き / 横棒 / 下向きで高中低を表す
        'priority-high' => '<path d="m5 14 7-7 7 7"/><path d="m5 19 7-7 7 7"/>',
        'priority-medium' => '<path d="M4 9h16M4 15h16"/>',
        'priority-low' => '<path d="m5 5 7 7 7-7"/><path d="m5 10 7 7 7-7"/>',
        // 鎖を断つ形。サブタスクの「外す」に使う
        'unlink' => '<path d="M9 15l6-6"/><path d="M11 6.5 12.5 5a4 4 0 0 1 5.7 5.7L16.7 12"/><path d="M13 17.5 11.5 19a4 4 0 0 1-5.7-5.7L7.3 12"/><path d="m3 3 18 18"/>',
        // Enter キーの記号。送信ボタンに使う
        'enter' => '<path d="M20 5v6a3 3 0 0 1-3 3H5"/><path d="m9 10-4 4 4 4"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'icon', 'aria-hidden' => 'true']) }}
     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
     stroke-linecap="round" stroke-linejoin="round">
    {!! $paths[$name] ?? '' !!}
</svg>
