<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 詳細画面での期限の変更。空で送れば未設定に戻る。
 */
class DueDateController extends Controller
{
    public function __invoke(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            ['due_date' => ['nullable', 'date']],
            attributes: ['due_date' => '期限'],
        );

        $task->update(['due_date' => $validated['due_date'] ?? null]);

        return back()->with('status', $task->due_date === null
            ? "{$task->key()} の期限を外しました。"
            : "{$task->key()} の期限を {$task->due_date->isoFormat('YYYY/M/D')} にしました。");
    }
}
