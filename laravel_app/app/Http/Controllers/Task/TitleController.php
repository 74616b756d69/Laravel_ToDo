<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 詳細画面でのタイトルの書き換え。
 *
 * 見出しをクリックすればその場で直せる。タイトルを 1 文字直すために
 * 編集フォームを開いて全項目を読み込ませる必要はない。
 */
class TitleController extends Controller
{
    public function __invoke(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            ['title' => ['required', 'string', 'max:100']],
            attributes: ['title' => 'タイトル'],
        );

        $task->update($validated);

        return back()->with('status', "{$task->key()} のタイトルを変更しました。");
    }
}
