<?php

namespace App\Http\Controllers;

use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Http\Requests\Task\TaskRequest;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
use App\Services\IssueLinkService;
use App\Services\WorkflowService;
use App\Support\IssueTimeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflows,
        private readonly IssueLinkService $links,
    ) {}

    /**
     * 並び替えの選択肢。キーはクエリ文字列、値は画面表示のラベル。
     */
    private const SORTS = [
        'latest' => '新しい順',
        'oldest' => '古い順',
        'due_date' => '期限が近い順',
        'priority' => '優先度が高い順',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $tasks = $this->query()
            ->with('tags', 'project', 'assignee', 'status')
            // 進捗バー用に子課題の件数だけを取得する（N+1 を避ける）
            ->withCount([
                'children',
                'children as done_children_count' => fn (Builder $query) => $query->completed(),
            ])
            ->search($filters['keyword'])
            ->status($filters['status'])
            ->category($filters['category'])
            ->priority($filters['priority'])
            ->tagged($filters['tag'])
            ->when($filters['overdue'], fn (Builder $query) => $query->overdue())
            ->sorted($filters['sort'])
            ->paginate(10)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'summary' => $this->summary(),
            'sorts' => self::SORTS,
            'tags' => Auth::user()->tags,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): View
    {
        $project = Project::personalFor(Auth::user());

        $task = new Issue(['priority' => TaskPriority::Medium]);
        // 新規は初期ステータス（レーンのいちばん手前）から始まる
        $task->setRelation('status', $project->initialStatus());

        return view('tasks.create', [
            'task' => $task,
            'tags' => Auth::user()->tags,
            'statuses' => $project->statuses()->get(),
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        // プロジェクト選択 UI はまだ無いので、個人プロジェクトへ入れる
        $project = Project::personalFor($request->user());

        $this->authorize('create', [Issue::class, $project]);

        $status = $request->status();

        $task = $project->createIssue([
            ...$request->taskAttributes(),
            // 作成は遷移ではないので、指定されたステータスをそのまま初期値にする
            'status_id' => $status->id,
            'completed_at' => $status->isDone() ? now() : null,
            'reporter_id' => $request->user()->id,
            'assignee_id' => $request->user()->id,
        ]);

        $task->tags()->sync($request->tagIds());

        return redirect()->route('tasks.show', $task)
            ->with('status', "「{$task->title}」を追加しました。");
    }

    /**
     * 課題詳細。下部のタブで、コメント・履歴・その両方を時系列で見せる。
     */
    public function show(Request $request, Issue $task): View
    {
        $this->authorize('view', $task);

        $task->load(
            'tags', 'project', 'assignee', 'reporter', 'status', 'sprint', 'parent',
            // 子は 1 行にステータス・担当者・キーまで出すので、そこまで読む
            'children.status', 'children.assignee', 'children.project',
            'comments.user', 'activities.user',
        );

        $tab = IssueTimeline::tab($request->query('tab'));

        return view('tasks.show', [
            'task' => $task,
            'tab' => $tab,
            'timeline' => IssueTimeline::build($task, $tab),
            'linkedIssues' => $this->links->groupedFor($task),
        ]);
    }

    public function edit(Issue $task): View
    {
        $this->authorize('update', $task);

        return view('tasks.edit', [
            'task' => $task->load('tags', 'status'),
            'tags' => Auth::user()->tags,
            // 現在地と、そこから行ける先だけを選べるようにする
            'statuses' => $this->workflows->availableFor($task)->prepend($task->status),
        ]);
    }

    public function update(TaskRequest $request, Issue $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->update($request->taskAttributes());
        // ステータスだけは検査を通す。禁止された遷移ならここで例外になる
        $this->workflows->transition($task, $request->status());
        $task->tags()->sync($request->tagIds());

        return redirect()->route('tasks.show', $task)
            ->with('status', "「{$task->title}」を更新しました。");
    }

    public function destroy(Issue $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('tasks.index')
            ->with('status', "「{$task->title}」を削除しました。");
    }

    /**
     * 一覧に出す課題のクエリ。
     *
     * 自分が参加しているプロジェクトの課題すべてが対象。サブタスクは
     * 親の詳細画面に表示するので、単独のカードとしては並べない。
     *
     * @return Builder<Issue>
     */
    private function query(): Builder
    {
        return Issue::query()->visibleTo(Auth::user())->topLevel();
    }

    /**
     * 絞り込みに使えるステータス。
     *
     * 参加プロジェクトが複数あると同名のステータスが並びうるので、
     * プロジェクトをまたぐときだけキーを添えて区別する。
     *
     * @return \Illuminate\Support\Collection<int, Status>
     */
    private function statuses()
    {
        return Status::query()
            ->whereHas('project.members', fn (Builder $query) => $query->where('user_id', Auth::id()))
            ->with('project')
            ->ordered()
            ->get();
    }

    /**
     * 一覧上部のダッシュボード用集計。
     *
     * ステータス名はプロジェクトごとに違うので、集計はカテゴリ単位で行う。
     *
     * @return array<string, int>
     */
    private function summary(): array
    {
        // カテゴリ別の件数を 1 クエリで取得する
        $counts = $this->query()
            ->join('statuses', 'statuses.id', '=', 'tasks.status_id')
            ->selectRaw('statuses.category as category, count(*) as aggregate')
            ->groupBy('statuses.category')
            ->pluck('aggregate', 'category');

        $byCategory = collect(StatusCategory::cases())
            ->mapWithKeys(fn (StatusCategory $category) => [
                $category->value => (int) $counts->get($category->value, 0),
            ]);

        return [
            ...$byCategory,
            'total' => $byCategory->sum(),
            'overdue' => $this->query()->overdue()->count(),
        ];
    }

    /**
     * クエリ文字列を検証済みの絞り込み条件に変換する。
     *
     * @return array{keyword: ?string, status: ?int, category: ?StatusCategory, priority: ?TaskPriority, tag: ?int, overdue: bool, sort: string}
     */
    private function filters(Request $request): array
    {
        $sort = (string) $request->query('sort');

        return [
            'keyword' => $request->string('keyword')->trim()->value() ?: null,
            'status' => $request->integer('status') ?: null,
            // 集計カードからの絞り込み。ステータス名ではなくカテゴリで横断する
            'category' => StatusCategory::tryFrom((string) $request->query('category')),
            'priority' => TaskPriority::tryFrom((string) $request->query('priority')),
            'tag' => $request->integer('tag') ?: null,
            'overdue' => $request->boolean('overdue'),
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'latest',
        ];
    }
}
