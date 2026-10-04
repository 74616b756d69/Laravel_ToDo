@extends('layouts.app')

@section('title', 'CSV から取り込む')

@section('content')
    <x-page-heading title="CSV から取り込む" :back="route('tasks.index')" back-label="一覧に戻る" />

    <p class="-mt-4 mb-6 text-sm text-slate-500 dark:text-slate-400">
        取り込み先: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $project->key }} — {{ $project->name }}</span>
        （ヘッダーで切り替えられます）
    </p>

    <form action="{{ route('tasks.import.store') }}" method="POST" enctype="multipart/form-data" class="card space-y-4 p-5 sm:p-6">
        @csrf
        <div>
            <label for="import-file" class="field-label">CSV ファイル</label>
            <input id="import-file" name="file" type="file" required accept=".csv,text/csv"
                   class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200 dark:file:bg-white/10 dark:hover:file:bg-white/15">
            <x-input-error :messages="$errors->get('file')" />
        </div>

        <ul class="list-disc space-y-1 pl-5 text-xs text-slate-500 dark:text-slate-400">
            <li>1 行目は見出し。使う列: {{ implode('・', \App\Support\Csv\IssueCsv::IMPORTABLE) }}（「タイトル」以外は省略できます）</li>
            <li>一覧の「CSV エクスポート」で書き出したファイルも、そのまま読み込めます（キーや日時の列は無視します）</li>
            <li>担当者はプロジェクトのメンバーのメールアドレスで指定します。タグは , 区切りで、無ければあなたのタグとして作ります</li>
            <li>UTF-8 と Shift_JIS（Excel で保存したもの）に対応。最大 {{ \App\Services\IssueImporter::MAX_ROWS }} 行・2 MB まで</li>
            <li>1 行でも誤りがあれば 1 件も取り込みません。結果の画面で何行目がなぜだめかを確認できます</li>
        </ul>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-white/5">
            <a href="{{ route('tasks.import.template') }}" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:underline dark:text-brand-300">
                <x-icon name="download" class="size-4" /> テンプレートをダウンロード
            </a>
            <button type="submit" class="btn-primary px-5 py-2.5">取り込む</button>
        </div>
    </form>

    @if ($imports->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-3 text-lg font-bold tracking-tight">最近の取り込み</h2>
            <div class="card overflow-hidden">
                <ul class="divide-y divide-slate-100 dark:divide-white/5">
                    @foreach ($imports as $import)
                        <li>
                            <a href="{{ route('tasks.import.show', $import) }}"
                               class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm transition hover:bg-slate-50 dark:hover:bg-white/5">
                                <x-badge :classes="$import->status->badgeClasses()">{{ $import->status->label() }}</x-badge>
                                <span class="min-w-0 flex-1 truncate">{{ $import->original_name }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $import->project->key }}</span>
                                @if ($import->status === \App\Enums\ImportStatus::Completed)
                                    <span class="text-xs text-slate-500 tabular-nums dark:text-slate-400">{{ $import->imported_rows }} 件</span>
                                @endif
                                <time class="text-xs text-slate-500 dark:text-slate-400" datetime="{{ $import->created_at->toIso8601String() }}">{{ $import->created_at->diffForHumans() }}</time>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
