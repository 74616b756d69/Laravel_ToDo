<?php

namespace App\Http\Controllers\Issue;

use App\Http\Controllers\Controller;
use App\Support\IssueReference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 課題キーで課題に辿り着く（/browse/PROJ-123）。
 *
 * 詳細画面の URL は移行の都合で /tasks/{id} のままなので、
 * ここは「キー → 詳細」の入口としてリダイレクトに徹する。
 *
 * 見えない課題と存在しないキーは、どちらも 404。
 * 「権限が無い」と返すと、他プロジェクトに何番まで課題があるかが漏れる。
 */
class BrowseController extends Controller
{
    public function __invoke(Request $request, string $key): RedirectResponse
    {
        $issue = IssueReference::resolveKeyFor($key, $request->user()) ?? abort(404);

        return redirect()->route('tasks.show', $issue);
    }
}
