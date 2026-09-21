<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
use App\Services\IssueOrderingService;
use App\Services\WorkflowService;
use App\Support\ProjectContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflows,
        private readonly IssueOrderingService $ordering,
        private readonly ProjectContext $context,
    ) {}

    public function index(): View
    {
        // ボードは 1 プロジェクトのワークフローを映すものなので、
        // レーンの出どころはヘッダーで選ばれているプロジェクトに従う
        $project = $this->context->current(Auth::user());

        $statuses = $project->statuses()->get();

        $tasks = Issue::query()
            ->visibleTo(Auth::user())
            ->topLevel()
            ->where('project_id', $project->id)
            ->with('tags', 'project', 'assignee', 'status')
            ->withCount([
                'children',
                'children as done_children_count' => fn (Builder $query) => $query
                    ->whereHas('status', fn (Builder $query) => $query->where('category', 'done')),
            ])
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();

        return view('board.index', [
            'project' => $project,
            // レーンは statuses から動的に作る。空のレーンも必ず用意する
            'lanes' => $statuses->map(fn (Status $status) => [
                'status' => $status,
                'tasks' => $tasks->where('status_id', $status->id)->values(),
            ]),
            // どのレーンへ運べるかを画面側で判定するための表
            'allowedTransitions' => $this->allowedTransitionMap($project, $statuses),
        ]);
    }

    /**
     * ドラッグ＆ドロップの結果を保存する。
     * 移動先レーンの並び順をまとめて受け取り、position を振り直す。
     */
    public function move(Request $request, Issue $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', 'integer'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // 他プロジェクトのステータスを指定されても弾けるよう、課題側から引く
        $target = $task->project->statuses()->findOrFail($validated['status']);

        // 許可されていない遷移なら例外。IllegalTransitionException が 422 を返し、
        // 画面側がカードを元の位置に戻して理由を表示する
        $this->workflows->assertAllowed($task, $target);

        DB::transaction(function () use ($task, $target, $validated) {
            $this->workflows->transition($task, $target);
            $this->ordering->apply(Auth::user(), $validated['ids']);
        });

        $task->refresh();

        return response()->json([
            'status' => $target->id,
            'statusName' => $target->name,
            'completed_at' => $task->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * 「どのレーンからどのレーンへ運べるか」の表。
     *
     * from のステータス ID => 運べる to のステータス ID の配列。
     * 自分自身（レーン内の並べ替え）は常に含める。
     *
     * @param  Collection<int, Status>  $statuses
     * @return array<int, array<int, int>>
     */
    private function allowedTransitionMap(Project $project, Collection $statuses): array
    {
        $transitions = $project->transitions()->get();
        $hasRules = $transitions->isNotEmpty();

        return $statuses->mapWithKeys(fn (Status $from) => [
            $from->id => $statuses
                ->filter(function (Status $to) use ($from, $transitions, $hasRules) {
                    if ($from->id === $to->id) {
                        return true;
                    }

                    // 遷移が 1 本も定義されていなければ全許可（WorkflowService と同じ扱い）
                    if (! $hasRules) {
                        return true;
                    }

                    return $transitions->contains(
                        fn ($transition) => $transition->to_status_id === $to->id
                            && ($transition->from_status_id === null
                                || $transition->from_status_id === $from->id),
                    );
                })
                ->pluck('id')
                ->values()
                ->all(),
        ])->all();
    }
}
