<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class TaskCompletionController extends Controller
{
    /**
     * 一覧のチェックボックスから完了 / 未完了を切り替える。
     */
    public function __invoke(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->toggleCompletion();

        return back()->with('status', $task->isCompleted()
            ? "「{$task->title}」を完了にしました。"
            : "「{$task->title}」を未着手に戻しました。");
    }
}
