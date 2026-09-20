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
