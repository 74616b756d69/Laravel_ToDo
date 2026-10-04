<?php

namespace App\Http\Controllers\Task;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ImportIssues;
use App\Models\Issue;
use App\Models\IssueImport;
use App\Support\Csv\IssueCsv;
use App\Support\ProjectContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV からの課題の取り込み。取り込み先はヘッダーで選んでいるプロジェクト。
 *
 * 取り込みそのものはキュー（ImportIssues）で行い、ここはファイルを預かって結果を見せるだけ。
 * 取り込みの記録は取り込んだ本人にしか見せない（中身はファイル名と行ごとのエラー）。
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly ProjectContext $context,
    ) {}

    public function create(Request $request): View
    {
        $project = $this->context->current($request->user());
        $this->authorize('create', [Issue::class, $project]);

        return view('tasks.import', [
            'project' => $project,
            'imports' => IssueImport::query()
                ->where('user_id', $request->user()->id)
                ->with('project')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $project = $this->context->current($request->user());
        $this->authorize('create', [Issue::class, $project]);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt', 'extensions:csv'],
        ], attributes: ['file' => 'CSV ファイル']);

        $file = $validated['file'];

        $import = new IssueImport;
        $import->forceFill([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            // 公開ディレクトリの外（local ディスク = storage/app/private）に置く
            'path' => $file->store('imports', 'local'),
            'status' => ImportStatus::Pending,
        ])->save();

        ImportIssues::dispatch($import);

        return redirect()->route('tasks.import.show', $import);
    }

    public function show(Request $request, IssueImport $import): View
    {
        // 他人の取り込みは「無い」と答える
        abort_unless($import->user_id === $request->user()->id, 404);

        return view('tasks.import-result', ['import' => $import->load('project')]);
    }

    /**
     * 見出しだけ（と記入例 1 行）のテンプレート。
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, IssueCsv::IMPORTABLE, escape: '');
            fputcsv($out, ['ログイン画面のデザイン修正', 'ボタンの色をブランドカラーに揃える', 'タスク', '', '中', '', '3', '120', today()->addWeek()->format('Y-m-d'), 'デザイン,UI'], escape: '');
            fclose($out);
        }, 'tracklet-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
