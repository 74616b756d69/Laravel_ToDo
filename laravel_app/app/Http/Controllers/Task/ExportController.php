<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Support\Csv\IssueCsv;
use App\Support\Search\IssueFilters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 一覧の CSV エクスポート。いま一覧で絞り込んでいる条件のまま、全ページぶんを書き出す。
 *
 * 件数が多くてもメモリを使い切らないよう、少しずつ読みながら流す。
 * 文字コードは UTF-8（BOM 付き）。BOM が無いと、Excel が Shift_JIS と読み違えて文字化けする。
 */
class ExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $filters = IssueFilters::fromRequest($request);
        $user = $request->user();

        $query = Issue::query()
            ->visibleTo($user)
            ->topLevel()
            ->with('project', 'status', 'assignee', 'reporter', 'sprint', 'tags')
            ->withSum('worklogs', 'minutes')
            ->tap(fn ($query) => $filters->apply($query, $user));

        $filename = 'tracklet-issues-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, IssueCsv::HEADERS, escape: '');

            // 並び順を保ったまま少しずつ読む（lazy は並び替えを崩さない）
            foreach ($query->lazy(200) as $issue) {
                fputcsv($out, IssueCsv::row($issue), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
