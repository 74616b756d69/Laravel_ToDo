<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\User;
use App\Services\IssueAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 詳細画面からの担当者の付け替え。「自分に割り当てる」もここを通る。
 */
class AssigneeController extends Controller
{
    public function __construct(private readonly IssueAssignmentService $assignments) {}

    public function __invoke(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            [
                // 空文字は「未割り当て」。候補はこのプロジェクトのメンバーだけ
                'assignee' => [
                    'nullable', 'integer',
                    Rule::exists('project_members', 'user_id')->where('project_id', $task->project_id),
                ],
            ],
            attributes: ['assignee' => '担当者'],
        );

        $assignee = blank($validated['assignee'] ?? null)
            ? null
            : User::findOrFail($validated['assignee']);

        $this->assignments->assign($task, $assignee);

        return back()->with('status', $assignee === null
            ? "{$task->key()} の担当者を外しました。"
            : "{$task->key()} の担当者を {$assignee->name} にしました。");
    }
}
