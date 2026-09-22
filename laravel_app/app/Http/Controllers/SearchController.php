<?php

namespace App\Http\Controllers;

use App\Support\IssueReference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ヘッダーの検索窓。
 *
 * 課題キーを打ったら詳細へ直行し、それ以外は一覧のキーワード検索へ流す。
 * 探すこと自体はどちらも既存の動線がやるので、ここは振り分けだけを持つ。
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $keyword = trim((string) $request->query('q'));

        if ($keyword === '') {
            return redirect()->route('tasks.index');
        }

        if (IssueReference::looksLikeKey($keyword)) {
            return redirect()->route('tasks.show', $keyword);
        }

        return redirect()->route('tasks.index', ['keyword' => $keyword]);
    }
}
