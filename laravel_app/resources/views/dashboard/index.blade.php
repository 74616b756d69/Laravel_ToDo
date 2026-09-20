@extends('layouts.app')

@section('title', '分析')

@php
    // --- 完了数の推移（単一系列の縦棒）のジオメトリ ---
    $width = 560; $height = 180;
    $padL = 30; $padR = 8; $padT = 14; $padB = 26;
    $plotW = $width - $padL - $padR;
    $plotH = $height - $padT - $padB;
    $band = $plotW / max($trend->count(), 1);
    $barW = min(24, $band - 8);
    $peak = max($trend->max('count'), 1);
    $baseline = $padT + $plotH;

    // --- 優先度別内訳（順序尺度の積み上げ横棒）---
    $openTotal = $byPriority->sum('count');
    $rampTokens = ['high' => 'var(--viz-ramp-high)', 'medium' => 'var(--viz-ramp-mid)', 'low' => 'var(--viz-ramp-low)'];
@endphp

@section('content')
    <div class="animate-rise space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">分析</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">タスクの消化ペースと残りの内訳を確認できます。</p>
        </div>

        {{-- KPI --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat-card label="総タスク" :value="$totals['total']" />
            <x-stat-card label="未完了" :value="$totals['open']" accent="text-sky-600 dark:text-sky-300" />
            <x-stat-card label="期限切れ" :value="$totals['overdue']" accent="text-rose-600 dark:text-rose-400"
                         :href="route('tasks.index', ['overdue' => 1])" />
            <div class="card flex flex-col gap-1 px-4 py-3">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">連続達成</span>
                <span class="text-2xl font-bold tabular-nums">
                    {{ $streak }}<span class="ml-0.5 text-sm font-medium text-slate-400">日</span>
                </span>
            </div>
        </div>

        {{-- 完了率（メーター） --}}
        <div class="card p-5">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold">完了率</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        今週の完了 {{ $totals['completedThisWeek'] }} 件
                    </p>
                </div>
                <p class="text-4xl font-bold tabular-nums">{{ $totals['rate'] }}<span class="text-xl">%</span></p>
            </div>
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                 role="progressbar" aria-valuenow="{{ $totals['rate'] }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="完了率">
                <div class="h-full rounded-full bg-brand-600 transition-[width] duration-500"
                     style="width: {{ $totals['rate'] }}%"></div>
            </div>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                {{ $totals['total'] }} 件中 {{ $totals['done'] }} 件が完了
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- 完了数の推移 --}}
            <section class="card viz p-5">
                <h2 class="text-sm font-semibold">日別の完了数</h2>
                <p class="mt-0.5 mb-3 text-xs text-slate-500 dark:text-slate-400">直近 {{ $trend->count() }} 日間</p>

                @if ($trend->sum('count') === 0)
                    <p class="py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                        まだ完了したタスクがありません。
                    </p>
                @else
                    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" role="img"
                         aria-label="直近{{ $trend->count() }}日間の日別完了数">
                        {{-- 目盛り線（背景より一段だけ濃いヘアライン） --}}
                        @foreach ([0, $peak] as $tick)
                            @php $y = $baseline - ($tick / $peak) * $plotH; @endphp
                            <line x1="{{ $padL }}" y1="{{ $y }}" x2="{{ $width - $padR }}" y2="{{ $y }}"
                                  stroke="var(--viz-grid)" stroke-width="1" />
                            <text x="{{ $padL - 6 }}" y="{{ $y + 4 }}" text-anchor="end"
                                  class="fill-slate-400 text-[10px] tabular-nums">{{ $tick }}</text>
                        @endforeach

                        <g class="viz-bars">
                            @foreach ($trend as $index => $point)
                                @php
                                    $h = $point['count'] === 0 ? 0 : max(3, ($point['count'] / $peak) * $plotH);
                                    $x = $padL + $band * $index + ($band - $barW) / 2;
                                @endphp
                                @if ($h > 0)
                                    {{-- 上端だけ 4px の丸み、ベースラインは角のまま --}}
                                    <path class="viz-bar"
                                          d="M{{ $x }},{{ $baseline }} V{{ $baseline - $h + 4 }}
                                             a4,4 0 0 1 4,-4 h{{ $barW - 8 }} a4,4 0 0 1 4,4
                                             V{{ $baseline }} Z"
                                          fill="var(--viz-series)">
                                        <title>{{ $point['date']->isoFormat('M月D日(ddd)') }}：{{ $point['count'] }} 件</title>
                                    </path>
                                @endif

                                {{-- 目盛りラベルは間引いて重なりを防ぐ --}}
                                @if ($index % 3 === 0 || $index === $trend->count() - 1)
                                    <text x="{{ $x + $barW / 2 }}" y="{{ $height - 8 }}" text-anchor="middle"
                                          class="fill-slate-400 text-[10px] tabular-nums">{{ $point['date']->format('n/j') }}</text>
                                @endif
                            @endforeach
                        </g>

                        {{-- ピークだけ直接ラベルを付ける --}}
                        @php
                            $peakIndex = $trend->search(fn ($p) => $p['count'] === $peak);
                            $peakX = $padL + $band * $peakIndex + $band / 2;
                            $peakY = $baseline - ($peak / $peak) * $plotH;
                        @endphp
                        <text x="{{ $peakX }}" y="{{ $peakY - 5 }}" text-anchor="middle"
                              class="fill-slate-500 text-[10px] font-semibold tabular-nums dark:fill-slate-300">{{ $peak }}</text>
                    </svg>
                @endif
            </section>

            {{-- 優先度別の内訳 --}}
            <section class="card viz p-5">
                <h2 class="text-sm font-semibold">未完了タスクの優先度</h2>
                <p class="mt-0.5 mb-4 text-xs text-slate-500 dark:text-slate-400">合計 {{ $openTotal }} 件</p>

                @if ($openTotal === 0)
                    <p class="py-10 text-center text-sm text-slate-400 dark:text-slate-500">未完了のタスクはありません。</p>
                @else
                    <svg viewBox="0 0 560 28" class="w-full" role="img" aria-label="未完了タスクの優先度別内訳">
                        {{-- 外周だけを丸めるためのクリップ。内側の区切りは 2px の背景色の隙間で表現する --}}
                        <clipPath id="stack-clip">
                            <rect x="0" y="0" width="560" height="28" rx="8" />
                        </clipPath>
                        <g clip-path="url(#stack-clip)">
                            @php $offset = 0; @endphp
                            @foreach ($byPriority as $row)
                                @php
                                    $segment = $row['count'] / $openTotal * 560;
                                    $x = $offset;
                                    $offset += $segment;
                                @endphp
                                @if ($row['count'] > 0)
                                    <rect x="{{ $x }}" y="0" width="{{ max($segment - 2, 1) }}" height="28"
                                          fill="{{ $rampTokens[$row['priority']->value] }}">
                                        <title>優先度{{ $row['priority']->label() }}：{{ $row['count'] }} 件</title>
                                    </rect>
                                @endif
                            @endforeach
                        </g>
                    </svg>

                    {{-- 凡例を兼ねた表。色だけに頼らず件数と割合を文字でも示す --}}
                    <dl class="mt-4 space-y-2">
                        @foreach ($byPriority as $row)
                            <div class="flex items-center gap-2.5 text-sm">
                                <span class="size-2.5 shrink-0 rounded-sm"
                                      style="background: {{ $rampTokens[$row['priority']->value] }}"></span>
                                <dt class="text-slate-600 dark:text-slate-300">優先度{{ $row['priority']->label() }}</dt>
                                <dd class="ml-auto tabular-nums">
                                    <span class="font-medium">{{ $row['count'] }}</span>
                                    <span class="ml-1 text-xs text-slate-400">
                                        {{ $openTotal === 0 ? 0 : round($row['count'] / $openTotal * 100) }}%
                                    </span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- 期限が近いタスク --}}
            <section class="card overflow-hidden">
                <h2 class="border-b border-slate-100 px-5 py-3.5 text-sm font-semibold dark:border-white/5">
                    期限が近いタスク
                </h2>
                @if ($upcoming->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-400 dark:text-slate-500">
                        1 週間以内に期限が来るタスクはありません。
                    </p>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($upcoming as $task)
                            <li>
                                <a href="{{ route('tasks.show', $task) }}"
                                   class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                                    <span class="min-w-0 flex-1 truncate text-sm">{{ $task->title }}</span>
                                    <x-badge :classes="$task->isOverdue()
                                            ? 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30'
                                            : 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30'">
                                        {{ $task->due_date->isoFormat('M/D(ddd)') }}
                                    </x-badge>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- よく使うタグ --}}
            <section class="card overflow-hidden">
                <h2 class="border-b border-slate-100 px-5 py-3.5 text-sm font-semibold dark:border-white/5">
                    よく使うタグ
                </h2>
                @if ($topTags->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-400 dark:text-slate-500">
                        タグが付いたタスクはまだありません。
                    </p>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($topTags as $tag)
                            <li>
                                <a href="{{ route('tasks.index', ['tag' => $tag->id]) }}"
                                   class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                                    <x-badge :classes="$tag->color->badgeClasses()" :dot="$tag->color->swatchClasses()">
                                        {{ $tag->name }}
                                    </x-badge>
                                    <span class="ml-auto text-sm tabular-nums">{{ $tag->tasks_count }} 件</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection
