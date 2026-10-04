@extends('layouts.app')

@section('title', 'Webhook | '.$project->name)

@section('content')
    <x-page-heading title="Webhook" :back="route('projects.edit', $project)" :back-label="$project->name.' の設定'" />

    <p class="-mt-4 mb-6 text-sm text-slate-500 dark:text-slate-400">
        課題の作成・ステータスの変更・担当者の変更・コメントを、Slack や外部のサーバーへ知らせます。
        送信はキューで行い、失敗したら時間をおいて最大 {{ count(config('webhooks.backoff')) + 1 }} 回まで送り直します。
    </p>

    @if ($webhooks->isNotEmpty())
        <div class="card mb-8 overflow-hidden">
            <ul class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($webhooks as $webhook)
                    @php($last = $webhook->deliveries->first())
                    <li>
                        <a href="{{ route('projects.webhooks.show', [$project, $webhook]) }}"
                           class="flex flex-wrap items-center gap-3 px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-white/5">
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-2 text-sm font-medium">
                                    {{ $webhook->name }}
                                    <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ $webhook->format->label() }}</span>
                                    @unless ($webhook->is_active)
                                        <x-badge classes="bg-slate-200 text-slate-600 ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700">無効</x-badge>
                                    @endunless
                                </p>
                                <p class="truncate font-mono text-xs text-slate-500 dark:text-slate-400">{{ $webhook->maskedUrl() }}</p>
                            </div>

                            @if ($last)
                                <span class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    最後の配信
                                    <x-badge :classes="$last->status->badgeClasses()">{{ $last->status->label() }}</x-badge>
                                    <time datetime="{{ $last->created_at->toIso8601String() }}">{{ $last->created_at->diffForHumans() }}</time>
                                </span>
                            @else
                                <span class="text-xs text-slate-400 dark:text-slate-500">まだ配信していません</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <h2 class="mb-3 text-lg font-bold tracking-tight">Webhook を追加</h2>
    @include('projects.webhooks.form', ['webhook' => null, 'action' => route('projects.webhooks.store', $project)])
@endsection
