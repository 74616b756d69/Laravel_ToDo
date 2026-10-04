@props(['issue', 'withDate' => false])

{{--
    期限。近いもの・過ぎたものだけ相対表示（今日 / 明日 / あと2日 / 13日超過）にする。

    「9/21 期限切れ」だと、どれだけ遅れているかを今日の日付から暗算しないと分からない。
    一方で遠い期限まで「あと17日」にすると、文字が増えるうえに日付が読めなくなる。
    そのため、行動が要る範囲（3 日以内と超過）だけを相対にし、それ以外は日付のまま置く。

    強調は色と文字だけにして札（背景）にはしない。期限切れが多い一覧で
    赤い札が並ぶと、行の中でいちばん強いものが期限になってしまうため。
    正確な日付はいつでもホバーで確認できるようにしておく。

    詳細画面のように場所に余裕があるところでは withDate で日付も併記する。
--}}
@if ($issue->due_date)
    @php
        $due = $issue->due_date;
        $days = $issue->daysUntilDue();
        $weekday = ['日', '月', '火', '水', '木', '金', '土'][$due->dayOfWeek];
        $fullDate = $due->format('Y/n/j')."（{$weekday}）";
        $date = $due->year === today()->year ? $due->format('n/j') : $due->format('Y/n/j');

        $relative = match (true) {
            $issue->isOverdue() => abs($days).'日超過',
            $issue->isCompleted() => null,
            $days === 0 => '今日',
            $days === 1 => '明日',
            $issue->isDueSoon() => "あと{$days}日",
            default => null,
        };

        $tone = match (true) {
            $issue->isOverdue() => 'font-medium text-rose-600 dark:text-rose-400',
            $issue->isDueSoon() => 'font-medium text-amber-600 dark:text-amber-400',
            default => 'text-slate-500 dark:text-slate-400',
        };
    @endphp

    <span title="期限 {{ $fullDate }}"
          {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 text-xs whitespace-nowrap tabular-nums '.$tone]) }}>
        <x-icon :name="$issue->isOverdue() ? 'alert' : 'calendar'" class="size-3.5" />
        @if ($relative === null)
            {{ $date }}
        @elseif ($withDate)
            {{ $date }}<span class="font-normal">（{{ $relative }}）</span>
        @else
            {{ $relative }}
        @endif
        <span class="sr-only">（期限 {{ $fullDate }}）</span>
    </span>
@endif
