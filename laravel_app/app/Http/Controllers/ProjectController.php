<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\Project\ProjectRequest;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        // 自分が所属しているものだけ。役割は中間テーブルから同じクエリで取れる
        $projects = Auth::user()->projects()->withCount('members')->get();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        return view('projects.create', ['project' => new Project]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $user = $request->user();

        $project = DB::transaction(function () use ($user, $request) {
            // Phase 1 では組織を明示的に作らせず、個人用の組織にぶら下げる
            $project = Organization::personalFor($user)
                ->projects()
                ->create($request->validated());

            // 作成者は必ず管理者として参加させる
            $project->members()->create(['user_id' => $user->id, 'role' => ProjectRole::Admin]);

            return $project;
        });

        return redirect()->route('projects.edit', $project)
            ->with('status', "プロジェクト「{$project->name}」を作成しました。");
    }

    /**
     * プロジェクト設定。メンバーは全員開けるが、編集できるのは管理者だけ。
     * 編集可否の出し分けは view 側の @can に任せる。
     */
    public function edit(Project $project): View
    {
        $this->authorize('view', $project);

        // ワークフロー欄で件数と遷移先の名前まで出すので、そこまで読む
        $project->load([
            'organization',
            'users',
            'statuses' => fn ($query) => $query->withCount('issues'),
            'transitions' => fn ($query) => $query->with('fromStatus', 'toStatus'),
        ]);

        return view('projects.edit', compact('project'));
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return redirect()->route('projects.edit', $project)
            ->with('status', "プロジェクト「{$project->name}」を更新しました。");
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')
            ->with('status', "プロジェクト「{$project->name}」を削除しました。");
    }
}
