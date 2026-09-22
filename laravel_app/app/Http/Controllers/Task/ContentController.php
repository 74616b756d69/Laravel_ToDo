<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 詳細画面での説明の書き換え。
 *
 * 受け取った HTML のサニタイズは Issue の content ミューテータが行うので、
 * ここでは丈だけを見る（コメントと同じ 2000 文字）。
 */
class ContentController extends Controller
{
    public function __invoke(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            ['content' => ['nullable', 'string', 'max:2000']],
            attributes: ['content' => '内容'],
        );

        $task->update(['content' => $validated['content'] ?? null]);

        return back()->with('status', "{$task->key()} の説明を更新しました。");
    }
}
