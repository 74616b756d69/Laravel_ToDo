<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\SubtaskRequest;
use App\Models\Issue;
use App\Services\IssueHierarchyService;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;

/**
 * サブタスク＝親を持つ課題。
 *
 * 新しく作ることも、既存の課題を引き込むこともできる。
 * どちらになるかは入力の中身で決まる（IssueHierarchyService::addFrom）。
 * URL とルート名（subtasks.*）は移行前から据え置き。
 */
class SubtaskController extends Controller
{
    public function __construct(private readonly IssueHierarchyService $hierarchy) {}

    public function store(SubtaskRequest $request, Issue $task): RedirectResponse
    {
        $child = $this->hierarchy->addFrom(
            $task,
            $request->validated()['title'],
            $request->user()->id,
        );

        return back()->with('status', $child->wasRecentlyCreated
            ? 'サブタスクを追加しました。'
            : "{$child->key()} をサブタスクにしました。");
    }

    public function toggle(Issue $task, Issue $subtask, WorkflowService $workflows): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->ensureBelongsTo($task, $subtask);

        $workflows->toggleCompletion($subtask);

        return back();
    }

    /**
     * 親子を外す。課題そのものは残り、一覧やボードに戻る。
     */
    public function detach(Issue $task, Issue $subtask): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->ensureBelongsTo($task, $subtask);

        $this->hierarchy->detach($subtask);

        return back()->with('status', "{$subtask->key()} をサブタスクから外しました。");
    }

    /**
     * 課題ごと削除する。
     *
     * 引き込んだ既存課題を「外す」つもりで消してしまわないよう、
     * ここで作られたサブタスクだけを対象にする。
     */
    public function destroy(Issue $task, Issue $subtask): RedirectResponse
    {
        $this->authorize('delete', $subtask);
        $this->ensureBelongsTo($task, $subtask);

        abort_unless(
            $subtask->issue_type->isSubtask(),
            422,
            'この課題は独立した課題です。削除ではなく「外す」を使ってください。',
        );

        $subtask->forceDelete();

        return back()->with('status', 'サブタスクを削除しました。');
    }

    /**
     * URL の組み合わせを差し替えて他の課題の子を操作されないようにする。
     */
    private function ensureBelongsTo(Issue $task, Issue $subtask): void
    {
        abort_unless($subtask->parent_id === $task->id, 404);
    }
}
