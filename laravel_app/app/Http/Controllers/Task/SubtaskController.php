<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubtaskController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            ['title' => ['required', 'string', 'max:120']],
            attributes: ['title' => 'サブタスク'],
        );

        $task->subtasks()->create([
            'title' => $validated['title'],
            // 末尾に追加する
            'position' => (int) $task->subtasks()->max('position') + 1,
        ]);

        return back()->with('status', 'サブタスクを追加しました。');
    }

    public function toggle(Task $task, Subtask $subtask): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->ensureBelongsTo($task, $subtask);

        $subtask->update(['is_done' => ! $subtask->is_done]);

        return back();
    }

    public function destroy(Task $task, Subtask $subtask): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->ensureBelongsTo($task, $subtask);

        $subtask->delete();

        return back()->with('status', 'サブタスクを削除しました。');
    }

    /**
     * URL の組み合わせを差し替えて他タスクのサブタスクを操作されないようにする。
     */
    private function ensureBelongsTo(Task $task, Subtask $subtask): void
    {
        abort_unless($subtask->task_id === $task->id, 404);
    }
}
