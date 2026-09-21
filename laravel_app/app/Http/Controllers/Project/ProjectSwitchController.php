<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\ProjectContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ヘッダーのプロジェクト切り替え。
 *
 * 切り替えても見ている画面は変えない（ボードで切り替えたらボードのまま）。
 * back() なのは、どの画面から切り替えても同じ動きになるようにするため。
 */
class ProjectSwitchController extends Controller
{
    public function __invoke(Request $request, ProjectContext $context): RedirectResponse
    {
        $validated = $request->validate(
            ['project' => ['required', 'integer', 'exists:projects,id']],
            attributes: ['project' => 'プロジェクト'],
        );

        $project = Project::findOrFail($validated['project']);

        // 所属していないプロジェクトへは切り替えさせない
        $this->authorize('view', $project);

        $context->switchTo($project);

        return back()->with('status', "プロジェクトを「{$project->name}」に切り替えました。");
    }
}
