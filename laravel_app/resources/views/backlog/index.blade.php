@extends('layouts.app')

@section('title', 'バックログ')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold tracking-tight">バックログ</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold tracking-wider text-slate-600 dark:bg-white/5 dark:text-slate-300">
                    {{ $project->key }}
                </span>
                課題をドラッグしてスプリントとバックログの間を移動できます。
            </p>
        </div>

        @if ($canManage)
            <details class="relative">
                <summary class="btn-primary cursor-pointer list-none">
                    <x-icon name="plus" class="size-4" /> スプリントを作成
                </summary>

                <form action="{{ route('sprints.store') }}" method="POST"
                      class="card absolute right-0 z-10 mt-2 w-80 space-y-3 p-4 shadow-lg">
                    @csrf
                    <div>
                        <label for="sprint-name" class="field-label">スプリント名 <span class="text-rose-500">*</span></label>
                        <input id="sprint-name" name="name" type="text" required maxlength="60"
                               value="{{ old('name') }}" placeholder="Sprint 1" class="field">
                        <x-input-error :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <label for="sprint-goal" class="field-label">ゴール</label>
                        <textarea id="sprint-goal" name="goal" rows="2" maxlength="500"
                                  placeholder="このスプリントで何を達成するか" class="field">{{ old('goal') }}</textarea>
                        <x-input-error :messages="$errors->get('goal')" />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="sprint-start" class="field-label">開始日</label>
                            <input id="sprint-start" name="start_date" type="date"
                                   value="{{ old('start_date') }}" class="field">
                        </div>
                        <div>
                            <label for="sprint-end" class="field-label">終了日</label>
                            <input id="sprint-end" name="end_date" type="date"
                                   value="{{ old('end_date') }}" class="field">
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('start_date')" />
                    <x-input-error :messages="$errors->get('end_date')" />

                    <button type="submit"
                            class="btn-primary w-full">
                        作成
                    </button>
                </form>
            </details>
        @endif
    </div>

    {{-- スプリントの開始・完了でエラーになったときの説明 --}}
    @if ($errors->has('sprint'))
        <div role="alert"
             class="mb-4 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">
            <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
            <p>{{ $errors->first('sprint') }}</p>
        </div>
    @endif

    <div data-backlog class="space-y-3">
        {{-- 上段: 各スプリント --}}
        @foreach ($sprints as $row)
            @php($sprint = $row['sprint'])
            <section class="card overflow-hidden">
                <header class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-white/5 dark:bg-white/[0.02]">
                    <x-badge :classes="$sprint->state->badgeClasses()" :dot="$sprint->state->dotClasses()">
                        {{ $sprint->state->label() }}
                    </x-badge>

                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $sprint->name }}</p>
                        @if ($sprint->goal)
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $sprint->goal }}</p>
                        @endif
                    </div>

                    @if ($sprint->period())
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $sprint->period() }}</span>
                    @endif

                    <span class="ml-auto text-xs font-medium text-slate-500 tabular-nums dark:text-slate-400">
                        <span data-sprint-count="{{ $sprint->id }}">{{ $row['issues']->count() }}</span> 件
                        @php($points = $row['issues']->sum('story_points'))
                        @if ($points > 0)
                            / {{ $points }} pt
                        @endif
                    </span>

                    @if ($canManage)
                        @if ($sprint->isFuture())
                            <form action="{{ route('sprints.start', $sprint) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="btn-quiet px-2.5 py-1 text-xs">
                                    開始
                                </button>
                            </form>
                        @elseif ($sprint->isActive())
                            <a href="{{ route('sprints.complete', $sprint) }}"
                               class="btn-quiet px-2.5 py-1 text-xs">
                                完了する
                            </a>
                        @endif
                    @endif
                </header>

                {{-- data-sprint の値が移動先になる --}}
                <ul data-sprint="{{ $sprint->id }}" data-sprint-name="{{ $sprint->name }}"
                    class="min-h-14 divide-y divide-slate-100 dark:divide-white/5">
                    @foreach ($row['issues'] as $issue)
                        @include('backlog.card', ['issue' => $issue])
                    @endforeach

                    <li data-lane-empty
                        class="{{ $row['issues']->isEmpty() ? '' : 'hidden' }} px-4 py-5 text-center text-xs text-slate-400 dark:text-slate-500">
                        ここに課題をドロップ
                    </li>
                </ul>
            </section>
        @endforeach

        {{-- 下段: バックログ --}}
        <section class="card overflow-hidden">
            <header class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-white/5 dark:bg-white/[0.02]">
                <h2 class="text-sm font-semibold">バックログ</h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">まだスプリントに入れていない課題</span>
                <span class="ml-auto text-xs font-medium text-slate-500 tabular-nums dark:text-slate-400">
                    <span data-sprint-count="backlog">{{ $backlog->count() }}</span> 件
                </span>
            </header>

            <ul data-sprint="" data-sprint-name="バックログ" class="min-h-14 divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($backlog as $issue)
                    @include('backlog.card', ['issue' => $issue])
                @endforeach

                <li data-lane-empty
                    class="{{ $backlog->isEmpty() ? '' : 'hidden' }} px-4 py-5 text-center text-xs text-slate-400 dark:text-slate-500">
                    バックログは空です
                </li>
            </ul>
        </section>
    </div>

    {{-- 移動に失敗したときの説明。JS が書き込む --}}
    <div data-backlog-error role="alert"
         class="mt-4 hidden items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">
        <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
        <p data-backlog-error-message></p>
    </div>

    <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
        @if ($closedCount > 0)
            完了したスプリントが {{ $closedCount }} 件あります（この画面には表示していません）。
        @endif
        ※ ドラッグ＆ドロップには JavaScript が必要です。
    </p>
@endsection
