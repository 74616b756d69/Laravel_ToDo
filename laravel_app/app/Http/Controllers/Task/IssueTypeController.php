<?php

namespace App\Http\Controllers\Task;

use App\Enums\IssueType;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 詳細画面からの課題タイプの変更。
 *
 * 種別は課題の性質（Bug / Story）を表すもので、親を持つかどうかとは別の軸。
 * だから parent_id には触らない（サブタスクのままタイプだけ直せる）。
 */
class IssueTypeController extends Controller
{
    public function __invoke(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(
            ['issue_type' => ['required', Rule::enum(IssueType::class)]],
            attributes: ['issue_type' => '課題タイプ'],
        );

        $task->update(['issue_type' => $validated['issue_type']]);

        return back()->with('status', "{$task->key()} を{$task->issue_type->label()}にしました。");
    }
}
