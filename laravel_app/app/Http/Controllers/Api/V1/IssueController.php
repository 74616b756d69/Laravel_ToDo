<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IssueType;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueApiRequest;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Services\IssueAssignmentService;
use App\Services\WorkflowService;
use App\Support\Search\IssueFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 課題の API。画面と同じサービス・同じ認可を通す。
 *
 * ステータスの変更は WorkflowService（許可された遷移か）、担当者は IssueAssignmentService
 * （メンバーか）を必ず通るので、API だからといってワークフローを飛び越えられない。
 * 履歴・通知・Webhook も画面からの操作と同じように残る・飛ぶ。
 */
class IssueController extends Controller
{
    private const WITH = ['project', 'status', 'assignee', 'reporter', 'tags'];

    public function __construct(
        private readonly WorkflowService $workflows,
        private readonly IssueAssignmentService $assignments,
    ) {}

    /**
     * 一覧。絞り込みは画面の一覧と同じクエリ（q / keyword / project / status / priority / sort …）。
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = IssueFilters::fromRequest($request);

        if ($filters->advanced->errors() !== []) {
            throw ValidationException::withMessages(['q' => $filters->advanced->errors()]);
        }

        $issues = Issue::query()
            ->visibleTo($request->user())
            ->topLevel()
            ->with(self::WITH)
            ->tap(fn ($query) => $filters->apply($query, $request->user()))
            ->paginate(min(100, max(1, $request->integer('per_page', 25))))
            ->withQueryString();

        return IssueResource::collection($issues);
    }

    public function show(Request $request, Issue $issue): IssueResource
    {
        $this->authorize('view', $issue);

        return new IssueResource($issue->load([...self::WITH, 'parent.project']));
    }

    public function store(IssueApiRequest $request, Project $project): JsonResponse
    {
        $status = $request->has('status') ? $request->status() : $project->initialStatus();
        $assignee = $request->filled('assignee_id') ? User::findOrFail($request->integer('assignee_id')) : null;

        $issue = $project->createIssue([
            'issue_type' => IssueType::Task,
            'priority' => TaskPriority::Medium,
            ...$request->issueAttributes(),
            // 作成は遷移ではないので、指定されたステータスをそのまま初期値にする（画面と同じ）
            'status_id' => $status->id,
            'completed_at' => $status->isDone() ? now() : null,
            'reporter_id' => $request->user()->id,
            'assignee_id' => $assignee?->id,
        ]);

        return (new IssueResource($issue->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(IssueApiRequest $request, Issue $issue): IssueResource
    {
        DB::transaction(function () use ($request, $issue) {
            $issue->update($request->issueAttributes());

            if ($request->has('status')) {
                $this->workflows->transition($issue, $request->status());
            }

            if ($request->has('assignee_id')) {
                $this->assignments->assign(
                    $issue,
                    $request->filled('assignee_id') ? User::findOrFail($request->integer('assignee_id')) : null,
                );
            }
        });

        return new IssueResource($issue->refresh()->load(self::WITH));
    }

    public function destroy(Issue $issue): Response
    {
        $this->authorize('delete', $issue);

        $issue->delete();

        return response()->noContent();
    }
}
