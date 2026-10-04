<?php

namespace App\Http\Controllers;

use App\Enums\IssueType;
use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Http\Requests\Task\TaskRequest;
use App\Models\Issue;
use App\Models\Status;
use App\Services\IssueLinkService;
use App\Services\WorkflowService;
use App\Support\IssueTimeline;
use App\Support\ProjectContext;
use App\Support\Search\IssueFilters;
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
        private readonly ProjectContext $context,
    ) {}

    public function index(Request $request): View
    {
        $filters = IssueFilters::fromRequest($request);

        $tasks = $this->query()
            ->with('tags', 'project', 'assignee', 'status')
            // 進捗バー用に子課題の件数だけを取得する（N+1 を避ける）
            ->withCount([
                'children',
                'children as done_children_count' => fn (Builder $query) => $query->completed(),
            ])
            ->tap(fn (Builder $query) => $filters->apply($query, $request->user()))
            ->paginate(10)
            ->withQueryString()
            // 現在地の前後 1 ページぶんだけを出す。11 ページあっても番号は折り返さない
            ->onEachSide(1);

        return view('tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters->values,
            'queryErrors' => $filters->advanced->errors(),
            'savedFilters' => $request->user()->savedFilters()->get(),
            'summary' => $this->summary(),
            'sorts' => IssueFilters::SORTS,
            'tags' => Auth::user()->tags,
            'statuses' => $this->statuses(),
            // 横断ビューのままにしておき、プロジェクトは絞り込みの 1 つとして足す
            'projects' => $this->context->available(Auth::user()),
        ]);
    }

    public function create(): View
    {
        $project = $this->context->current(Auth::user());

        $task = new Issue([
            'issue_type' => IssueType::Task,
            'priority' => TaskPriority::Medium,
        ]);
        // 新規は初期ステータス（レーンのいちばん手前）から始まる
        $task->setRelation('status', $project->initialStatus());
        // 既定の担当者は自分。未割り当てにもできる
        // （保存前の表示用モデルなので、fillable を通さず直接置く）
        $task->assignee_id = Auth::id();

        return view('tasks.create', [
            'task' => $task,
            'tags' => Auth::user()->tags,
            'statuses' => $project->statuses()->get(),
            'members' => $project->users,
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        // 作成先はヘッダーで選ばれているプロジェクト。採番もそこで行われる
        $project = $this->context->current($request->user());

        $this->authorize('create', [Issue::class, $project]);

        $status = $request->status();

        $task = $project->createIssue([
            ...$request->taskAttributes(),
            // 作成は遷移ではないので、指定されたステータスをそのまま初期値にする
            'status_id' => $status->id,
            'completed_at' => $status->isDone() ? now() : null,
            'reporter_id' => $request->user()->id,
            // 担当者の欄が無い経路（クイック追加など）では、これまでどおり自分に割り当てる
            'assignee_id' => $request->hasAssignee()
                ? $request->assignee()?->id
                : $request->user()->id,
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
            // 担当者の選択肢に project.users まで要る（遅延ロードを増やさない）
            'tags', 'project.users', 'assignee', 'reporter', 'status', 'sprint', 'parent',
            // 子は 1 行にステータス・担当者・キーまで出すので、そこまで読む
            'children.status', 'children.assignee', 'children.project',
            'comments.user', 'activities.user', 'attachments.user', 'worklogs.user',
        );
        $task->loadCount('watchers');

        $tab = IssueTimeline::tab($request->query('tab'));

        return view('tasks.show', [
            'task' => $task,
            'tab' => $tab,
            'timeline' => IssueTimeline::build($task, $tab),
            'linkedIssues' => $this->links->groupedFor($task),
            // 編集フォームへ行かずに動かせるよう、次に取れる遷移と候補を渡す
            'transitions' => $this->workflows->availableFor($task),
            'members' => $task->project->users,
            // タグはその場で付け替えられる。候補は自分が作ったものだけ。
            // リレーションのキャッシュに乗せず毎回引くので、描画に要るクエリ数は一定になる
            'tags' => $request->user()->tags()->get(),
            // 各項目を「押したら編集」にするか、読むだけにするかの分かれ目
            'canUpdate' => $request->user()->can('update', $task),
            'isWatching' => $task->isWatchedBy($request->user()),
        ]);
    }

    /*
     * 編集画面は持たない。
     *
     * 課題の書き換えは詳細画面のインライン編集（App\Http\Controllers\Task\ の
     * 各コントローラ）に一本化してある。1 項目を直すために全項目のフォームを
     * 開かせない、という詳細画面の作りと、入り口を 2 つ持たないため。
     */

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
}
