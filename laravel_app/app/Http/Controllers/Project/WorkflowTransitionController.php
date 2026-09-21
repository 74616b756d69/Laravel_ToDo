<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\TransitionRequest;
use App\Models\Project;
use App\Models\Transition;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;

/**
 * プロジェクト設定のワークフロー（遷移）編集。管理者だけが触れる。
 *
 * 遷移が 1 本も無いプロジェクトは全許可にフォールバックする作りなので、
 * 全部消しても画面は止まらない（制約が外れるだけ）。
 */
class WorkflowTransitionController extends Controller
{
    public function __construct(private readonly WorkflowService $workflows) {}

    public function store(TransitionRequest $request, Project $project): RedirectResponse
    {
        $from = $request->from();
        $to = $request->to();

        $this->workflows->addTransition($project, $from, $to);

        return back()->with('status', sprintf(
            '%s →「%s」の遷移を追加しました。',
            $from === null ? 'どの状態からでも' : "「{$from->name}」",
            $to->name,
        ));
    }

    public function destroy(Project $project, Transition $transition): RedirectResponse
    {
        $this->authorize('update', $project);

        $transition->delete();

        return back()->with('status', '遷移を削除しました。');
    }
}
