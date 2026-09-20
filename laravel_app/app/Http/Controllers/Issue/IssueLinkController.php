<?php

namespace App\Http\Controllers\Issue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\IssueLinkRequest;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Services\IssueLinkService;
use Illuminate\Http\RedirectResponse;

/**
 * リンクされた作業項目。
 *
 * サブタスク（親子）と違い、関連づけても相手は一覧・ボードに残る。
 */
class IssueLinkController extends Controller
{
    public function __construct(private readonly IssueLinkService $links) {}

    public function store(IssueLinkRequest $request, Issue $task): RedirectResponse
    {
        $link = $this->links->linkFrom($task, $request->validated()['target'], $request->type());

        return back()->with(
            'status',
            "{$link->counterpartFor($task)->key()} を関連づけました。",
        );
    }

    public function destroy(Issue $task, IssueLink $link): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->ensureBelongsTo($task, $link);

        $counterpart = $link->counterpartFor($task);

        $this->links->unlink($link);

        return back()->with('status', "{$counterpart->key()} との関連を外しました。");
    }

    /**
     * URL の組み合わせを差し替えて、無関係な関連を消されないようにする。
     */
    private function ensureBelongsTo(Issue $task, IssueLink $link): void
    {
        abort_unless(
            $link->source_issue_id === $task->id || $link->target_issue_id === $task->id,
            404,
        );
    }
}
