<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\DeleteStatusRequest;
use App\Http\Requests\Project\StatusRequest;
use App\Models\Project;
use App\Models\Status;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * プロジェクト設定のワークフロー（ステータス）編集。管理者だけが触れる。
 *
 * ルートは scopeBindings なので、他プロジェクトのステータス ID を
 * URL に混ぜても 404 になる。
 */
class StatusController extends Controller
{
    public function __construct(private readonly WorkflowService $workflows) {}

    public function store(StatusRequest $request, Project $project): RedirectResponse
    {
        $status = $this->workflows->addStatus($project, $request->validated());

        return back()->with('status', "ステータス「{$status->name}」を追加しました。");
    }

    public function update(StatusRequest $request, Project $project, Status $status): RedirectResponse
    {
        $this->workflows->updateStatus($status, $request->validated());

        return back()->with('status', "ステータス「{$status->name}」を更新しました。");
    }

    /**
     * レーンを 1 つ前後へ動かす。端では何も起きない。
     */
    public function move(Request $request, Project $project, Status $status): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate(
            ['direction' => ['required', 'in:up,down']],
            attributes: ['direction' => '移動方向'],
        );

        $this->workflows->moveStatus($status, $validated['direction']);

        return back();
    }

    /**
     * 削除の確認。残っている課題の移送先をここで選ばせる。
     *
     * スプリント完了と同じ 2 段構え。消えるものと行き先を見せてから実行する。
     */
    public function confirmDelete(Project $project, Status $status): View
    {
        $this->authorize('update', $project);

        return view('projects.statuses.delete', [
            'project' => $project,
            'status' => $status,
            'issues' => $status->issues()->with('project', 'assignee')->get(),
            'destinations' => $project->statuses()->whereKeyNot($status->getKey())->get(),
        ]);
    }

    public function destroy(DeleteStatusRequest $request, Project $project, Status $status): RedirectResponse
    {
        $name = $status->name;
        $destination = $request->destination();

        $moved = $this->workflows->deleteStatus($status, $destination);

        return redirect()->route('projects.edit', $project)->with('status', $moved === 0
            ? "ステータス「{$name}」を削除しました。"
            : "ステータス「{$name}」を削除し、残った {$moved} 件を「{$destination->name}」へ移しました。");
    }
}
