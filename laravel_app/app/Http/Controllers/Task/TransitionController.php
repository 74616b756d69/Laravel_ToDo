<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 詳細画面の遷移ボタン。
 *
 * 「次に行ける状態」だけがボタンとして並ぶので、通常ここに禁止された遷移は来ない。
 * それでも検査を省かないのは、URL を直接叩かれてもワークフローを破らせないため。
 */
class TransitionController extends Controller
{
    public function __invoke(Request $request, Issue $task, WorkflowService $workflows): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            ['status' => ['required', 'integer']],
            attributes: ['status' => 'ステータス'],
        );

        // 他プロジェクトのステータスを指定されても弾けるよう、課題側から引く
        $target = $task->project->statuses()->findOrFail($validated['status']);

        // 許可されていなければ IllegalTransitionException が理由を返す
        $workflows->transition($task, $target);

        return back()->with('status', "{$task->key()} を「{$target->name}」にしました。");
    }
}
