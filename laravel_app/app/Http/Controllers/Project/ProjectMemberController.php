<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ProjectMemberRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectMemberController extends Controller
{
    /**
     * 既存ユーザーをメールアドレスで招待する。
     */
    public function store(ProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $invitee = $request->invitee();

        $project->members()->create([
            'user_id' => $invitee->id,
            'role' => $request->validated()['role'],
        ]);

        return back()->with('status', "{$invitee->name} さんをプロジェクトに追加しました。");
    }
}
