<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 詳細画面での見積り（ストーリーポイント）の変更。
 *
 * スプリントの進捗はここの合計で出しているので、
 * 見積りを置き直すのに編集フォームを開かせない。空なら未設定に戻る。
 */
class StoryPointsController extends Controller
{
    public function __invoke(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            // カラムは unsignedSmallInteger。負の値と桁あふれをここで止める
            ['story_points' => ['nullable', 'integer', 'min:0', 'max:999']],
            attributes: ['story_points' => 'ストーリーポイント'],
        );

        $task->update(['story_points' => $validated['story_points'] ?? null]);

        return back()->with('status', $task->story_points === null
            ? "{$task->key()} の見積りを外しました。"
            : "{$task->key()} の見積りを {$task->story_points} にしました。");
    }
}
