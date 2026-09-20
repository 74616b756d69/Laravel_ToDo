@extends('layouts.app')

@section('title', '分析')

@php
    // --- 完了数の推移（単一系列の縦棒）のジオメトリ ---
    $width = 900; $height = 200;
    $padL = 32; $padR = 10; $padT = 18; $padB = 26;
    $plotW = $width - $padL - $padR;
    $plotH = $height - $padT - $padB;
    $band = $plotW / max($trend->count(), 1);
    $barW = min(24, $band - 10);
    $peak = max($trend->max('count'), 1);
    $baseline = $padT + $plotH;
    $peakIndex = $trend->search(fn ($point) => $point['count'] === $peak);

    // --- 優先度別内訳（順序尺度の積み上げ横棒）---
    $openTotal = $byPriority->sum('count');
    $ramp = ['high' => 'var(--viz-ramp-high)', 'medium' => 'var(--viz-ramp-mid)', 'low' => 'var(--viz-ramp-low)'];
@endphp

@section('content')
    <h1 class="text-2xl font-bold tracking-tight">分析</h1>

    {{--
        主要な数字は箱に入れず、1 本の帯として見せる。
        下の罫線を完了率のメーターと兼ねることで、指標と進み具合を同時に伝える。
    --}}
    <div class="mt-5 flex flex-wrap items-baseline gap-x-8 gap-y-3">
        <p class="flex items-baseline gap-1.5">
            <span class="text-3xl font-bold tabular-nums">{{ $totals['done'] }}</span>
            <span class="text-sm text-slate-400">/ {{ $totals['total'] }} 完了</span>
        </p>

        @foreach ([
            ['未完了', $totals['open'], ''],
            ['期限切れ', $totals['overdue'], $totals['overdue'] > 0 ? 'text-rose-600 dark:text-rose-400' : ''],
            ['連続', $streak.' 日', ''],
            ['今週', $totals['completedThisWeek'].' 件', ''],
        ] as [$label, $value, $accent])
            <p class="flex items-baseline gap-1.5">
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</span>
                <span class="text-lg font-semibold tabular-nums {{ $accent }}">{{ $value }}</span>
            </p>
        @endforeach

        <p class="ml-auto text-xs text-slate-400">直近 {{ $trend->count() }} 日間</p>
    </div>

    <div class="mt-3 h-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
         role="img" aria-label="完了率 {{ $totals['rate'] }}%">
        <div class="h-full bg-brand-600 dark:bg-brand-500" style="width: {{ $totals['rate'] }}%"></div>
    </div>
    <p class="mt-1.5 text-xs text-slate-400">完了率 {{ $totals['rate'] }}%</p>

    {{--
        バーンダウン。進行中スプリントがあるときだけ出す。
        実績（折れ線）と理想（破線）を重ねて、遅れているかどうかを一目で見せる。
    --}}
    @if ($burndown)
        @php
            $bw = 900; $bh = 200;
            $bPadL = 36; $bPadR = 12; $bPadT = 16; $bPadB = 26;
            $bPlotW = $bw - $bPadL - $bPadR;
            $bPlotH = $bh - $bPadT - $bPadB;
            $bTotal = max($burndown['total'], 1);
            $bLast = max($burndown['days']->count() - 1, 1);
            // 値 → 座標。残量 0 が下端、総量が上端
            $bx = fn (int $i) => $bPadL + $bPlotW * $i / $bLast;
            $by = fn (float $v) => $bPadT + $bPlotH * (1 - $v / $bTotal);

            $actual = $burndown['days']->filter(fn ($d) => $d['remaining'] !== null)->values();
            $actualPath = $actual
                ->map(fn ($d, $i) => ($i === 0 ? 'M' : 'L').round($bx($i), 1).','.round($by($d['remaining']), 1))
                ->implode(' ');
            $idealPath = 'M'.round($bx(0), 1).','.round($by($bTotal), 1)
                .' L'.round($bx($bLast), 1).','.round($by(0), 1);
            $remainingNow = $actual->last()['remaining'] ?? $bTotal;
            $idealNow = $burndown['days'][$actual->count() - 1]['ideal'] ?? $bTotal;
        @endphp

        <section class="viz mt-8">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 class="text-sm font-semibold">バーンダウン</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $burndown['sprint']->name }}
                    @if ($burndown['sprint']->period()) ・{{ $burndown['sprint']->period() }} @endif
                </span>
                <p class="ml-auto text-xs text-slate-400">
                    残り {{ $remainingNow }} / {{ $burndown['total'] }} {{ $burndown['unit'] }}
                    @if ($remainingNow > $idealNow)
                        <span class="text-rose-500 dark:text-rose-400">（理想より遅れ）</span>
                    @else
                        <span class="text-emerald-600 dark:text-emerald-400">（順調）</span>
                    @endif
                </p>
            </div>

            <svg viewBox="0 0 {{ $bw }} {{ $bh }}" class="mt-3 w-full" role="img"
                 aria-label="{{ $burndown['sprint']->name }}のバーンダウン。残り{{ $remainingNow }}{{ $burndown['unit'] }}">
                @foreach ([0, $bTotal] as $tick)
                    <line x1="{{ $bPadL }}" y1="{{ $by($tick) }}" x2="{{ $bw - $bPadR }}" y2="{{ $by($tick) }}"
                          stroke="var(--viz-grid)" stroke-width="1" />
                    <text x="{{ $bPadL - 8 }}" y="{{ $by($tick) + 4 }}" text-anchor="end"
                          class="fill-slate-400 text-[11px] tabular-nums">{{ $tick }}</text>
                @endforeach

                {{-- 理想線は破線。実績と competing しないよう細く薄く --}}
                <path d="{{ $idealPath }}" fill="none" stroke="var(--viz-grid)"
                      stroke-width="1.5" stroke-dasharray="4 4" />

                @if ($actual->count() > 1)
                    <path d="{{ $actualPath }}" fill="none" stroke="var(--viz-series)"
                          stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
                @endif

                @foreach ($actual as $i => $day)
                    <circle cx="{{ $bx($i) }}" cy="{{ $by($day['remaining']) }}" r="3"
                            fill="var(--viz-series)">
                        <title>{{ $day['date']->isoFormat('M月D日(ddd)') }}：残り {{ $day['remaining'] }} {{ $burndown['unit'] }}</title>
                    </circle>
                @endforeach

                @foreach ($burndown['days'] as $i => $day)
                    @if ($i % 2 === 0 || $i === $bLast)
                        <text x="{{ $bx($i) }}" y="{{ $bh - 8 }}" text-anchor="middle"
                              class="fill-slate-400 text-[11px] tabular-nums">{{ $day['date']->format('n/j') }}</text>
                    @endif
                @endforeach
            </svg>

            <p class="mt-1.5 text-xs text-slate-400">
                実線が実績、破線が理想。{{ $burndown['unit'] === 'ポイント' ? 'ストーリーポイント' : '課題の件数' }}で数えています。
            </p>
        </section>
    @endif

    {{-- この画面の主役。全幅で大きく取る --}}
    <section class="viz mt-8">
        <h2 class="text-sm font-semibold">日別の完了数</h2>

        @if ($trend->sum('count') === 0)
            <p class="mt-6 border-t border-slate-200 py-12 text-center text-sm text-slate-400 dark:border-slate-800 dark:text-slate-500">
                まだ完了したタスクがありません。
            </p>
        @else
            <svg viewBox="0 0 {{ $width }} {{ $height }}" class="mt-3 w-full" role="img"
                 aria-label="直近{{ $trend->count() }}日間の日別完了数">
                @foreach ([0, $peak] as $tick)
                    @php $y = $baseline - ($tick / $peak) * $plotH; @endphp
                    <line x1="{{ $padL }}" y1="{{ $y }}" x2="{{ $width - $padR }}" y2="{{ $y }}"
                          stroke="var(--viz-grid)" stroke-width="1" />
                    <text x="{{ $padL - 8 }}" y="{{ $y + 4 }}" text-anchor="end"
                          class="fill-slate-400 text-[11px] tabular-nums">{{ $tick }}</text>
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
                                  class="fill-slate-400 text-[11px] tabular-nums">{{ $point['date']->format('n/j') }}</text>
                        @endif
                    @endforeach
                </g>

                {{-- ピークだけ直接ラベルを付ける --}}
                <text x="{{ $padL + $band * $peakIndex + $band / 2 }}" y="{{ $padT - 5 }}" text-anchor="middle"
                      class="fill-slate-500 text-[11px] font-semibold tabular-nums dark:fill-slate-300">{{ $peak }}</text>
            </svg>
        @endif
    </section>

    <div class="mt-8 grid gap-8 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        {{-- 優先度の内訳 --}}
        <section class="viz">
            <h2 class="text-sm font-semibold">
                未完了の優先度
                <span class="ml-1 font-normal text-slate-400">{{ $openTotal }} 件</span>
            </h2>

            @if ($openTotal === 0)
                <p class="mt-3 border-t border-slate-200 py-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:text-slate-500">
                    未完了のタスクはありません。
                </p>
            @else
                <svg viewBox="0 0 560 20" class="mt-3 w-full" role="img" aria-label="未完了タスクの優先度別内訳">
                    {{-- 外周だけを丸め、内側の区切りは 2px の隙間で表す --}}
                    <clipPath id="stack-clip"><rect x="0" y="0" width="560" height="20" rx="4" /></clipPath>
                    <g clip-path="url(#stack-clip)">
                        @php $offset = 0; @endphp
                        @foreach ($byPriority as $row)
                            @php
                                $segment = $row['count'] / $openTotal * 560;
                                $x = $offset;
                                $offset += $segment;
                            @endphp
                            @if ($row['count'] > 0)
                                <rect x="{{ $x }}" y="0" width="{{ max($segment - 2, 1) }}" height="20"
                                      fill="{{ $ramp[$row['priority']->value] }}">
                                    <title>優先度{{ $row['priority']->label() }}：{{ $row['count'] }} 件</title>
                                </rect>
                            @endif
                        @endforeach
                    </g>
                </svg>

                {{-- 凡例と表を兼ねる。色だけに頼らず件数と割合を文字でも示す --}}
                <dl class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($byPriority as $row)
                        <div class="flex items-center gap-2.5 py-2 text-sm">
                            <span class="size-2.5 shrink-0 rounded-xs" style="background: {{ $ramp[$row['priority']->value] }}"></span>
                            <dt class="text-slate-600 dark:text-slate-300">{{ $row['priority']->label() }}</dt>
                            <dd class="ml-auto tabular-nums">
                                {{ $row['count'] }}
                                <span class="ml-1 text-xs text-slate-400">
                                    {{ round($row['count'] / $openTotal * 100) }}%
                                </span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        {{-- 期限が近いタスク --}}
        <section>
            <h2 class="text-sm font-semibold">対応が必要なタスク</h2>

            @if ($upcoming->isEmpty())
                <p class="mt-3 border-t border-slate-200 py-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:text-slate-500">
                    期限が迫っているタスクはありません。
                </p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($upcoming as $task)
                        <li>
                            <a href="{{ route('tasks.show', $task) }}"
                               class="flex items-baseline gap-3 py-2.5 hover:text-brand-700 dark:hover:text-brand-300">
                                <span class="w-14 shrink-0 text-xs tabular-nums {{ $task->isOverdue() ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                                    {{ $task->due_date->isoFormat('M/D(ddd)') }}
                                </span>
                                <span class="min-w-0 flex-1 truncate text-sm">{{ $task->title }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- タグは件数の多い順に、幅で量がわかるよう横一列に並べる --}}
    @if ($topTags->isNotEmpty())
        <section class="mt-8">
            <h2 class="text-sm font-semibold">よく使うタグ</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($topTags as $tag)
                    <li>
                        <a href="{{ route('tasks.index', ['tag' => $tag->id]) }}"
                           class="inline-flex items-baseline gap-2 rounded-full px-3 py-1.5 text-xs ring-1 ring-inset {{ $tag->color->badgeClasses() }}">
                            {{ $tag->name }}
                            <span class="font-semibold tabular-nums">{{ $tag->issues_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
