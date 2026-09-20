<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;

class TaskCompletionController extends Controller
{
    /**
     * 一覧のチェックボックスから完了 / 未完了を切り替える。
     */
    public function __invoke(Issue $task, WorkflowService $workflows): RedirectResponse
    {
        $this->authorize('update', $task);

        // 完了の切り替えもワークフローの検査を通す。
        // 許可されていなければ IllegalTransitionException が理由を返す
        $workflows->toggleCompletion($task);

        return back()->with('status', $task->isCompleted()
            ? "「{$task->title}」を完了にしました。"
            : "「{$task->title}」を未着手に戻しました。");
    }
}
