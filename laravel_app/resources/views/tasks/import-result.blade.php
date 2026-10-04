@extends('layouts.app')

@section('title', '取り込みの結果')

{{-- 終わるまでは数秒おきに読み直す（JS に頼らない） --}}
@unless ($import->status->isFinished())
    @push('head')
        <meta http-equiv="refresh" content="3">
    @endpush
@endunless

@section('content')
    <x-page-heading title="取り込みの結果" :back="route('tasks.import')" back-label="CSV から取り込む" />

    <div class="card space-y-3 p-5 sm:p-6">
        <p class="flex flex-wrap items-center gap-3">
            <x-badge :classes="$import->status->badgeClasses()">{{ $import->status->label() }}</x-badge>
            <span class="font-medium">{{ $import->original_name }}</span>
            <span class="text-sm text-slate-500 dark:text-slate-400">→ {{ $import->project->key }}</span>
        </p>

        @switch($import->status)
            @case(\App\Enums\ImportStatus::Pending)
            @case(\App\Enums\ImportStatus::Processing)
                <p class="text-sm text-slate-600 dark:text-slate-300" aria-live="polite">取り込んでいます。この画面は自動で更新されます…</p>
                @break

            @case(\App\Enums\ImportStatus::Completed)
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ $import->imported_rows }} 件の課題を作成しました。</p>
                <a href="{{ route('tasks.index', ['project' => $import->project_id, 'sort' => 'latest']) }}" class="btn-primary inline-flex px-4 py-2">一覧で見る</a>
                @break

            @case(\App\Enums\ImportStatus::Failed)
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    誤りがあったため、1 件も取り込んでいません。ファイルを直して、もう一度取り込んでください。
                </p>
                <div class="overflow-hidden rounded-xl border border-rose-200 dark:border-rose-500/30">
                    <table class="w-full text-sm">
                        <thead class="bg-rose-50 text-left text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                            <tr><th class="w-20 px-3 py-2 font-medium">行</th><th class="px-3 py-2 font-medium">内容</th></tr>
                        </thead>
                        <tbody class="divide-y divide-rose-100 dark:divide-rose-500/20">
                            @foreach ($import->errors ?? [] as $error)
                                <tr>
                                    <td class="px-3 py-2 tabular-nums text-slate-500 dark:text-slate-400">{{ $error['row'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $error['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @break
        @endswitch
    </div>
@endsection
