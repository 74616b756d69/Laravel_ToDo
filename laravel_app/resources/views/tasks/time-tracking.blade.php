{{--
    作業時間（見積もりと実績）。

    見積もりはその場で直せる 1 行の値、実績は記録の積み上げ。
    バーは「見積もりに対してどれだけ使ったか」。超えたら色を変えて知らせる。
--}}
@php
    $estimate = $task->original_estimate_minutes;
    $spent = $task->worklogs->sum('minutes');
    $remaining = $estimate === null ? null : max(0, $estimate - $spent);
    $over = $estimate !== null && $spent > $estimate;
    $percent = $estimate ? min(100, (int) round($spent / $estimate * 100)) : 0;
    $worklogBag = $errors->getBag('worklog');
@endphp

<section class="card text-sm" aria-labelledby="time-tracking-heading">
    <div class="space-y-3 px-4 py-3">
        <h2 id="time-tracking-heading" class="flex items-center gap-1.5 font-semibold">
            <x-icon name="clock" class="size-4 text-slate-400" /> 作業時間
        </h2>

        <div class="flex items-start gap-3">
            <span class="py-1 text-slate-500 dark:text-slate-400">見積もり</span>
            <div class="ml-auto min-w-0 flex-1">
                <x-inline-edit :editable="$canUpdate" :action="route('tasks.estimate', $task)"
                               field="estimate" label="見積もり時間" in-place trigger-class="-mr-2 justify-end py-1 pr-2">
                    <x-slot:display>
                        <span class="font-medium tabular-nums">{{ \App\Support\Duration::format($estimate) }}</span>
                    </x-slot:display>

                    <input name="estimate" type="text" maxlength="20" placeholder="3h / 1d"
                           value="{{ old('estimate', \App\Support\Duration::toInput($estimate)) }}"
                           class="field w-24 px-2 py-1 text-right text-sm">
                    <x-input-error :messages="$errors->get('estimate')" />
                </x-inline-edit>
            </div>
        </div>

        <div>
            <div class="h-1.5 w-full overflow-hidden rounded-xs bg-slate-200 dark:bg-slate-700"
                 role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="見積もりに対する実績">
                <div @class([
                        'h-full transition-[width] duration-300',
                        'bg-brand-600 dark:bg-brand-400' => ! $over,
                        'bg-rose-500' => $over,
                     ]) style="width: {{ $estimate ? $percent : ($spent > 0 ? 100 : 0) }}%"></div>
            </div>
            <p class="mt-1.5 flex justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>実績 <span class="font-medium text-slate-700 tabular-nums dark:text-slate-200">{{ $spent > 0 ? \App\Support\Duration::format($spent) : '0分' }}</span></span>
                @if ($over)
                    <span class="font-medium text-rose-600 dark:text-rose-400">{{ \App\Support\Duration::format($spent - $estimate) }} 超過</span>
                @elseif ($remaining !== null)
                    <span>残り {{ $remaining > 0 ? \App\Support\Duration::format($remaining) : '0分' }}</span>
                @endif
            </p>
        </div>
    </div>

    @can('create', [\App\Models\Worklog::class, $task])
        {{-- 失敗したときは開いたまま戻す --}}
        <details class="border-t border-slate-100 dark:border-white/5" @if ($worklogBag->any()) open @endif>
            <summary class="cursor-pointer px-4 py-2.5 font-medium text-brand-700 hover:bg-slate-50 dark:text-brand-300 dark:hover:bg-white/5">
                ＋ 作業を記録
            </summary>
            <form action="{{ route('worklogs.store', $task) }}" method="POST" class="space-y-2 px-4 pb-3">
                @csrf
                <div class="flex gap-2">
                    <label class="flex-1">
                        <span class="sr-only">作業時間</span>
                        <input name="time" type="text" required maxlength="20" value="{{ old('time') }}"
                               placeholder="1h30m" class="field py-1.5 text-sm">
                    </label>
                    <label>
                        <span class="sr-only">作業日</span>
                        <input name="worked_on" type="date" required max="{{ today()->format('Y-m-d') }}"
                               value="{{ old('worked_on', today()->format('Y-m-d')) }}" class="field py-1.5 text-sm">
                    </label>
                </div>
                <label class="block">
                    <span class="sr-only">メモ</span>
                    <input name="comment" type="text" maxlength="255" value="{{ old('comment') }}"
                           placeholder="メモ（任意）" class="field py-1.5 text-sm">
                </label>
                <x-input-error :messages="$worklogBag->get('time')" />
                <x-input-error :messages="$worklogBag->get('worked_on')" />
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-slate-400">例: 45m・2h・1.5h・1d（8 時間）</span>
                    <button type="submit" class="btn-primary px-3 py-1.5 text-xs">記録</button>
                </div>
            </form>
        </details>
    @endcan

    @if ($task->worklogs->isNotEmpty())
        <ul class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-white/5 dark:border-white/5">
            @foreach ($task->worklogs as $worklog)
                <li class="flex items-start gap-2 px-4 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-baseline gap-2">
                            <span class="font-medium tabular-nums">{{ $worklog->duration() }}</span>
                            <span class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ $worklog->authorName() }} ・ {{ $worklog->worked_on->isoFormat('M/D (ddd)') }}
                            </span>
                        </p>
                        @if ($worklog->comment)
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $worklog->comment }}</p>
                        @endif
                    </div>
                    @can('delete', $worklog)
                        <form action="{{ route('worklogs.destroy', [$task, $worklog]) }}" method="POST"
                              data-confirm="{{ $worklog->duration() }} の記録を削除しますか？">
                            @csrf
                            @method('DELETE')
                            <button type="submit" aria-label="{{ $worklog->duration() }} の記録を削除" title="削除"
                                    class="grid size-6 place-items-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400">
                                <x-icon name="trash" class="size-3.5" />
                            </button>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
</section>
