@extends('layouts.app')

@section('title', $project->name.' の設定')

@section('content')
    <x-page-heading :title="$project->name" :back="route('projects.index')" back-label="プロジェクト一覧" />

    <p class="-mt-4 mb-6 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-semibold tracking-wider text-slate-600 dark:bg-white/5 dark:text-slate-300">
            {{ $project->key }}
        </span>
        {{ $project->organization->name }}
    </p>

    @can('update', $project)
        @include('projects.form', [
            'action' => route('projects.update', $project),
            'method' => 'PUT',
            'submitLabel' => '保存',
            'cancelUrl' => route('projects.index'),
        ])
    @else
        {{-- 管理者以外には読み取り専用で見せる --}}
        <div class="card space-y-3 p-5 sm:p-6">
            <div>
                <span class="field-label">プロジェクト名</span>
                <p class="text-sm">{{ $project->name }}</p>
            </div>
            <div>
                <span class="field-label">説明</span>
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ $project->description ?: '—' }}</p>
            </div>
            <p class="border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-white/5 dark:text-slate-400">
                プロジェクト設定を変更できるのは管理者だけです。
            </p>
        </div>
    @endcan

    {{-- メンバー --}}
    <section class="mt-8">
        <h2 class="mb-3 text-lg font-bold tracking-tight">メンバー</h2>

        @can('update', $project)
            <form action="{{ route('projects.members.store', $project) }}" method="POST"
                  class="card mb-4 space-y-4 p-5">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="email" class="field-label">メールアドレス</label>
                        <input id="email" name="email" type="email" required value="{{ old('email') }}"
                               placeholder="already-registered@example.com" class="field">
                    </div>

                    <div class="sm:w-40">
                        <label for="role" class="field-label">役割</label>
                        <select id="role" name="role" class="field">
                            @foreach (\App\Enums\ProjectRole::options() as $value => $label)
                                <option value="{{ $value }}"
                                        @selected(old('role', \App\Enums\ProjectRole::Member->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
                        <x-icon name="plus" class="size-4" /> 追加
                    </button>
                </div>

                <p class="text-xs text-slate-500 dark:text-slate-400">
                    登録済みのユーザーをメールアドレスで追加します。
                </p>

                <x-input-error :messages="$errors->get('email')" />
                <x-input-error :messages="$errors->get('role')" />
            </form>
        @endcan

        <div class="card overflow-hidden">
            <ul class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($project->users as $member)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $member->name }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $member->email }}</p>
                        </div>

                        <x-badge :classes="$member->membership->role->badgeClasses()">
                            {{ $member->membership->role->label() }}
                        </x-badge>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ワークフロー。レーンの本数も、動かせる順路もここで決まる --}}
    @can('update', $project)
        <section class="mt-8">
            <h2 class="mb-1 text-lg font-bold tracking-tight">ワークフロー</h2>
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
                ステータスはボードのレーンになり、遷移は「どこからどこへ動かせるか」を決めます。
            </p>

            <x-input-error :messages="$errors->get('workflow')" />

            {{-- ステータス --}}
            <div class="card mb-4 overflow-hidden">
                <ul class="divide-y divide-slate-100 dark:divide-white/5">
                    @foreach ($project->statuses as $index => $status)
                        <li class="flex flex-wrap items-center gap-2 px-4 py-3">
                            {{-- 並べ替えは上下ボタン。JS なしでも動く --}}
                            <div class="flex shrink-0 flex-col">
                                <form action="{{ route('projects.statuses.move', [$project, $status]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" aria-label="{{ $status->name }}を上へ"
                                            @disabled($index === 0)
                                            class="px-1 text-xs text-slate-400 hover:text-slate-900 disabled:opacity-30 dark:hover:text-white">▲</button>
                                </form>
                                <form action="{{ route('projects.statuses.move', [$project, $status]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" aria-label="{{ $status->name }}を下へ"
                                            @disabled($index === $project->statuses->count() - 1)
                                            class="px-1 text-xs text-slate-400 hover:text-slate-900 disabled:opacity-30 dark:hover:text-white">▼</button>
                                </form>
                            </div>

                            <form action="{{ route('projects.statuses.update', [$project, $status]) }}" method="POST"
                                  class="flex flex-1 flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <label class="sr-only" for="status-name-{{ $status->id }}">ステータス名</label>
                                <input id="status-name-{{ $status->id }}" name="name" type="text" required maxlength="40"
                                       value="{{ $status->name }}"
                                       class="min-w-32 flex-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">

                                <label class="sr-only" for="status-category-{{ $status->id }}">カテゴリ</label>
                                <select id="status-category-{{ $status->id }}" name="category"
                                        class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                                    @foreach (\App\Enums\StatusCategory::options() as $value => $label)
                                        <option value="{{ $value }}" @selected($status->category->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>

                                <button type="submit"
                                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium transition hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-white/5">
                                    保存
                                </button>
                            </form>

                            <span class="shrink-0 text-xs text-slate-500 tabular-nums dark:text-slate-400">
                                {{ $status->issues_count }} 件
                            </span>

                            {{-- 課題が残っていれば移送先を選ばせる画面へ送る --}}
                            <a href="{{ route('projects.statuses.delete', [$project, $status]) }}"
                               class="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                               aria-label="{{ $status->name }}を削除">
                                <x-icon name="trash" class="size-4" />
                            </a>
                        </li>
                    @endforeach
                </ul>

                <form action="{{ route('projects.statuses.store', $project) }}" method="POST"
                      class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-white/5 dark:bg-white/[0.02]">
                    @csrf
                    <label for="new-status-name" class="sr-only">追加するステータス名</label>
                    <input id="new-status-name" name="name" type="text" required maxlength="40"
                           value="{{ old('name') }}" placeholder="例：Blocked"
                           class="min-w-32 flex-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">

                    <label for="new-status-category" class="sr-only">カテゴリ</label>
                    <select id="new-status-category" name="category"
                            class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                        @foreach (\App\Enums\StatusCategory::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-700">
                        <x-icon name="plus" class="size-3.5" /> ステータスを追加
                    </button>
                </form>

                <div class="px-4 pb-3">
                    <x-input-error :messages="$errors->get('name')" />
                    <x-input-error :messages="$errors->get('category')" />
                </div>
            </div>

            {{-- 遷移 --}}
            <div class="card overflow-hidden">
                @if ($project->transitions->isEmpty())
                    <p class="px-4 py-4 text-sm text-slate-500 dark:text-slate-400">
                        遷移が 1 本もないので、いまはどのステータスへも自由に動かせます。
                        1 本でも登録すると、登録した順路だけが通れるようになります。
                    </p>
                @else
                    <ul class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($project->transitions as $transition)
                            <li class="flex flex-wrap items-center gap-2 px-4 py-2.5 text-sm">
                                @if ($transition->isGlobal())
                                    <span class="text-slate-500 dark:text-slate-400">どの状態からでも</span>
                                @else
                                    <x-badge :classes="$transition->fromStatus->badgeClasses()">
                                        {{ $transition->fromStatus->name }}
                                    </x-badge>
                                @endif

                                <span class="text-slate-400">→</span>

                                <x-badge :classes="$transition->toStatus->badgeClasses()">
                                    {{ $transition->toStatus->name }}
                                </x-badge>

                                <form action="{{ route('projects.transitions.destroy', [$project, $transition]) }}"
                                      method="POST" class="ml-auto">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" aria-label="この遷移を削除"
                                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form action="{{ route('projects.transitions.store', $project) }}" method="POST"
                      class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-white/5 dark:bg-white/[0.02]">
                    @csrf
                    <label for="transition-from" class="sr-only">変更前のステータス</label>
                    <select id="transition-from" name="from"
                            class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <option value="">どの状態からでも</option>
                        @foreach ($project->statuses as $status)
                            <option value="{{ $status->id }}" @selected((int) old('from') === $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>

                    <span class="text-slate-400">→</span>

                    <label for="transition-to" class="sr-only">変更後のステータス</label>
                    <select id="transition-to" name="to"
                            class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                        @foreach ($project->statuses as $status)
                            <option value="{{ $status->id }}" @selected((int) old('to') === $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>

                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-brand-700">
                        <x-icon name="plus" class="size-3.5" /> 遷移を追加
                    </button>

                    <x-input-error :messages="$errors->get('to')" />
                    <x-input-error :messages="$errors->get('from')" />
                </form>
            </div>
        </section>
    @endcan

    @can('delete', $project)
        {{-- 危険な操作は他と離して最後に置く --}}
        <section class="mt-8">
            <h2 class="mb-3 text-lg font-bold tracking-tight">プロジェクトの削除</h2>

            <div class="card flex flex-wrap items-center justify-between gap-3 border-rose-200 p-5 dark:border-rose-500/30">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    削除するとメンバーの所属も含めて元に戻せません。
                </p>

                <form action="{{ route('projects.destroy', $project) }}" method="POST"
                      data-confirm="プロジェクト「{{ $project->name }}」を削除します。よろしいですか？">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-300 dark:hover:bg-rose-500/10">
                        <x-icon name="trash" class="size-4" /> 削除する
                    </button>
                </form>
            </div>
        </section>
    @endcan
@endsection
