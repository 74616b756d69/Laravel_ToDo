@props(['issue'])

{{--
    期限。切れているときだけ札にして、それ以外は文字で置く。

    期限切れは手を打つ必要がある状態なので目立たせる価値があるが、
    ふつうの期限まで札にすると、期限のある課題すべてが赤くも黄色くも
    見える一覧になってしまう。強調は「異常なもの」だけに使う。
--}}
@if ($issue->due_date)
    @if ($issue->isOverdue())
        <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 rounded-md bg-rose-50 px-1.5 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-500/10 dark:text-rose-300']) }}>
            <x-icon name="alert" class="size-3.5" />
            {{ $issue->due_date->format('n/j') }} 期限切れ
        </span>
    @else
        <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 text-xs '.($issue->isDueSoon() ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400')]) }}>
            <x-icon name="calendar" class="size-3.5" />
            {{ $issue->due_date->format('n/j') }}
        </span>
    @endif
@endif
