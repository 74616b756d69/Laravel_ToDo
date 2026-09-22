<?php

namespace App\Http\Controllers\Issue;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 連番の id で貼られた古い詳細 URL（/tasks/99）を、課題キーの URL へ送る。
 *
 * 詳細の正式な URL は /browse/PROJ-99 になったが、
 * すでに貼られたリンクやブックマークを切らないために入口だけ残す。
 *
 * 見えない課題と存在しない id は、どちらも 404。
 * 「権限が無い」と返すと、他プロジェクトに何番まで課題があるかが漏れる。
 */
class LegacyUrlController extends Controller
{
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $issue = Issue::query()
            ->visibleTo($request->user())
            ->with('project')
            ->find((int) $id) ?? abort(404);

        return redirect()->route('tasks.show', $issue, 301);
    }
}
