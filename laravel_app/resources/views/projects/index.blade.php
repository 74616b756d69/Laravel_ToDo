@extends('layouts.app')

@section('title', 'プロジェクト')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">プロジェクト</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                自分が参加しているプロジェクトの一覧です。
            </p>
        </div>

        <a href="{{ route('projects.create') }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">
            <x-icon name="plus" class="size-4" /> 新規プロジェクト
        </a>
    </div>

    <div class="card overflow-hidden">
        @if ($projects->isEmpty())
            <x-empty-state title="プロジェクトがありません"
                           description="プロジェクトを作ると、メンバーを招いて課題を共有できるようになります。">
                <a href="{{ route('projects.create') }}"
                   class="mt-1 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700">
                    <x-icon name="plus" class="size-4" /> 最初のプロジェクトを作る
                </a>
            </x-empty-state>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($projects as $project)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3.5">
                        <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-semibold tracking-wider text-slate-600 dark:bg-white/5 dark:text-slate-300">
                            {{ $project->key }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $project->name }}</p>
                            @if ($project->description)
                                <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $project->description }}</p>
                            @endif
                        </div>

                        <x-badge :classes="$project->membership->role->badgeClasses()">
                            {{ $project->membership->role->label() }}
                        </x-badge>

                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $project->members_count }} 人
                        </span>

                        <a href="{{ route('projects.edit', $project) }}"
                           class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium transition hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-white/5">
                            設定
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
