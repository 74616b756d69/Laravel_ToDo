<?php

namespace App\Http\Controllers;

use App\Enums\SprintState;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\IssueOrderingService;
use App\Services\SprintService;
use App\Support\ProjectContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * バックログ画面。
 *
 * 上段に各スプリント、下段にバックログを並べ、課題をドラッグで行き来させる。
 * ボードが「いま何をしているか」なら、こちらは「いつやるか」を決める場所。
 */
class BacklogController extends Controller
{
    public function __construct(
        private readonly SprintService $sprints,
        private readonly IssueOrderingService $ordering,
        private readonly ProjectContext $context,
    ) {}

    public function index(): View
    {
        // スプリントもバックログもプロジェクト単位。ヘッダーの選択に従う
        $project = $this->context->current(Auth::user());

        $issues = $this->query($project)->get();

        // 完了したスプリントは畳んでおきたいので、進行中・未開始だけを上段に出す
        $sprints = $project->sprints()
            ->whereNot('state', SprintState::Closed)
            ->ordered()
            ->get();

        return view('backlog.index', [
            'project' => $project,
            'sprints' => $sprints->map(fn (Sprint $sprint) => [
                'sprint' => $sprint,
                'issues' => $issues->where('sprint_id', $sprint->id)->values(),
            ]),
            'backlog' => $issues->whereNull('sprint_id')->values(),
            'closedCount' => $project->sprints()->state(SprintState::Closed)->count(),
            'canManage' => Auth::user()->can('create', [Sprint::class, $project]),
        ]);
    }

    /**
     * 課題をスプリント間・バックログ間で移動する。
     *
     * ボードの move と同じ形で、移動先の並び順もまとめて受け取る。
     */
    public function move(Request $request, Issue $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            // 空文字・null はバックログを意味する
            'sprint' => ['nullable', 'integer'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $sprint = blank($validated['sprint'] ?? null)
            ? null
            // 他プロジェクトのスプリントを指定されても弾けるよう、課題側から引く
            : $task->project->sprints()->findOrFail($validated['sprint']);

        DB::transaction(function () use ($task, $sprint, $validated) {
            $this->sprints->assign($task, $sprint);
            $this->ordering->apply(Auth::user(), $validated['ids']);
        });

        return response()->json([
            'sprint' => $sprint?->id,
            'sprintName' => $sprint?->name ?? 'バックログ',
        ]);
    }

    /**
     * バックログに並べる課題。ボードと同じくサブタスクは単独で並べない。
     *
     * @return Builder<Issue>
     */
    private function query(Project $project): Builder
    {
        return Issue::query()
            ->visibleTo(Auth::user())
            ->topLevel()
            ->where('project_id', $project->id)
            ->with('tags', 'project', 'assignee', 'status')
            ->orderBy('position')
            ->orderByDesc('id');
    }
}
