<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Task\TaskRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TaskController extends Controller
{
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
            ->with('tags')
            // 進捗バー用にサブタスクの件数だけを取得する（N+1 を避ける）
            ->withCount([
                'subtasks',
                'subtasks as done_subtasks_count' => fn ($query) => $query->where('is_done', true),
            ])
            ->search($filters['keyword'])
            ->status($filters['status'])
            ->priority($filters['priority'])
            ->tagged($filters['tag'])
            ->when($filters['overdue'], fn ($query) => $query->overdue())
            ->sorted($filters['sort'])
            ->paginate(10)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'summary' => $this->summary(),
            'sorts' => self::SORTS,
            'tags' => Auth::user()->tags,
        ]);
    }

    public function create(): View
    {
        return view('tasks.create', [
            'task' => new Task(['status' => TaskStatus::Todo, 'priority' => TaskPriority::Medium]),
            'tags' => Auth::user()->tags,
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $task = Auth::user()->tasks()->create($request->taskAttributes());
        $task->tags()->sync($request->tagIds());

        return redirect()->route('tasks.show', $task)
            ->with('status', "「{$task->title}」を追加しました。");
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);

        $task->load('tags', 'subtasks');

        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);

        return view('tasks.edit', [
            'task' => $task->load('tags'),
            'tags' => Auth::user()->tags,
        ]);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->update($request->taskAttributes());
        $task->tags()->sync($request->tagIds());

        return redirect()->route('tasks.show', $task)
            ->with('status', "「{$task->title}」を更新しました。");
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('tasks.index')
            ->with('status', "「{$task->title}」を削除しました。");
    }

    /**
     * ログインユーザーのタスクだけを対象にしたクエリ。
     *
     * @return \Illuminate\Database\Eloquent\Builder<Task>
     */
    private function query()
    {
        return Auth::user()->tasks()->getQuery();
    }

    /**
     * 一覧上部のダッシュボード用集計。
     *
     * @return array<string, int>
     */
    private function summary(): array
    {
        // ステータス別の件数を 1 クエリで取得する
        $counts = $this->query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = collect(TaskStatus::cases())
            ->mapWithKeys(fn (TaskStatus $status) => [
                $status->value => (int) $counts->get($status->value, 0),
            ]);

        return [
            ...$byStatus,
            'total' => $byStatus->sum(),
            'overdue' => $this->query()->overdue()->count(),
        ];
    }

    /**
     * クエリ文字列を検証済みの絞り込み条件に変換する。
     *
     * @return array{keyword: ?string, status: ?TaskStatus, priority: ?TaskPriority, tag: ?int, overdue: bool, sort: string}
     */
    private function filters(Request $request): array
    {
        $sort = (string) $request->query('sort');

        return [
            'keyword' => $request->string('keyword')->trim()->value() ?: null,
            'status' => TaskStatus::tryFrom((string) $request->query('status')),
            'priority' => TaskPriority::tryFrom((string) $request->query('priority')),
            'tag' => $request->integer('tag') ?: null,
            'overdue' => $request->boolean('overdue'),
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'latest',
        ];
    }
}
