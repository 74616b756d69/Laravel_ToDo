<?php

namespace App\Http\Controllers\Sprint;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sprint\CompleteSprintRequest;
use App\Http\Requests\Sprint\SprintRequest;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\SprintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SprintController extends Controller
{
    public function __construct(private readonly SprintService $sprints) {}

    public function store(SprintRequest $request): RedirectResponse
    {
        $project = Project::personalFor($request->user());

        $this->authorize('create', [Sprint::class, $project]);

        $sprint = $this->sprints->create($project, $request->validated());

        return back()->with('status', "スプリント「{$sprint->name}」を作成しました。");
    }

    public function update(SprintRequest $request, Sprint $sprint): RedirectResponse
    {
        $this->authorize('update', $sprint);

        $sprint->update($request->validated());

        return back()->with('status', "スプリント「{$sprint->name}」を更新しました。");
    }

    /**
     * 開始（future → active）。
     * 同時に active にできるのは 1 つだけで、破れば SprintException が理由を返す。
     */
    public function start(Sprint $sprint): RedirectResponse
    {
        $this->authorize('transition', $sprint);

        $this->sprints->start($sprint);

        return back()->with('status', "スプリント「{$sprint->name}」を開始しました。");
    }

    /**
     * 完了の確認画面。残った課題をどこへ送るか選ばせる。
     */
    public function confirmComplete(Sprint $sprint): View
    {
        $this->authorize('transition', $sprint);

        return view('sprints.complete', [
            'sprint' => $sprint->load('project'),
            'incomplete' => $sprint->issues()->completed(false)->with('status', 'project')->get(),
            'completed' => $sprint->issues()->completed()->count(),
            'destinations' => $this->sprints->destinationsFor($sprint),
        ]);
    }

    public function complete(CompleteSprintRequest $request, Sprint $sprint): RedirectResponse
    {
        $this->authorize('transition', $sprint);

        $destination = $request->destination();
        $moved = $this->sprints->complete($sprint, $destination);

        $where = $destination?->name ?? 'バックログ';

        return redirect()->route('backlog')->with('status', $moved === 0
            ? "スプリント「{$sprint->name}」を完了しました。"
            : "スプリント「{$sprint->name}」を完了し、残った {$moved} 件を{$where}へ移しました。");
    }

    public function destroy(Sprint $sprint): RedirectResponse
    {
        $this->authorize('delete', $sprint);

        // 外部キーが nullOnDelete なので、入っていた課題はバックログへ戻る
        $sprint->delete();

        return back()->with('status', "スプリント「{$sprint->name}」を削除しました。課題はバックログへ戻しました。");
    }
}
