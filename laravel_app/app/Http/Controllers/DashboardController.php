<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** 完了数の推移を見る日数 */
    private const TREND_DAYS = 14;

    public function index(): View
    {
        return view('dashboard.index', [
            'totals' => $this->totals(),
            'trend' => $this->completionTrend(),
            'byPriority' => $this->openTasksByPriority(),
            'streak' => $this->streak(),
            'upcoming' => $this->upcoming(),
            'topTags' => $this->topTags(),
        ]);
    }

    /** @return Builder<Task> */
    private function query(): Builder
    {
        return Auth::user()->tasks()->getQuery();
    }

    /**
     * 画面上部の KPI。
     *
     * @return array<string, int>
     */
    private function totals(): array
    {
        $counts = $this->query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $done = (int) $counts->get(TaskStatus::Done->value, 0);
        $total = (int) $counts->sum();

        return [
            'total' => $total,
            'done' => $done,
            'open' => $total - $done,
            'overdue' => $this->query()->overdue()->count(),
            'rate' => $total === 0 ? 0 : (int) round($done / $total * 100),
            'completedThisWeek' => $this->query()
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', today()->startOfWeek())
                ->count(),
        ];
    }

    /**
     * 直近 N 日の日別完了数。データが無い日も 0 で埋めて連続した系列にする。
     *
     * @return Collection<int, array{date: Carbon, count: int}>
     */
    private function completionTrend(): Collection
    {
        $from = today()->subDays(self::TREND_DAYS - 1);

        $counts = $this->query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->get(['completed_at'])
            ->countBy(fn (Task $task) => $task->completed_at->toDateString());

        return collect(range(0, self::TREND_DAYS - 1))
            ->map(function (int $offset) use ($from, $counts) {
                $date = $from->copy()->addDays($offset);

                return [
                    'date' => $date,
                    'count' => (int) $counts->get($date->toDateString(), 0),
                ];
            });
    }

    /**
     * 未完了タスクの優先度別内訳（高い順）。
     *
     * @return Collection<int, array{priority: TaskPriority, count: int}>
     */
    private function openTasksByPriority(): Collection
    {
        $counts = $this->query()
            ->whereNot('status', TaskStatus::Done)
            ->selectRaw('priority, count(*) as aggregate')
            ->groupBy('priority')
            ->pluck('aggregate', 'priority');

        return collect([TaskPriority::High, TaskPriority::Medium, TaskPriority::Low])
            ->map(fn (TaskPriority $priority) => [
                'priority' => $priority,
                'count' => (int) $counts->get($priority->value, 0),
            ]);
    }

    /**
     * 今日（または昨日）から遡って、何日連続でタスクを完了しているか。
     */
    private function streak(): int
    {
        $days = $this->query()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', today()->subDays(365))
            ->get(['completed_at'])
            ->map(fn (Task $task) => $task->completed_at->toDateString())
            ->unique()
            ->flip();

        // 今日まだ完了が無くても、昨日までの連続記録は途切れていないものとして数える
        $cursor = $days->has(today()->toDateString()) ? today() : today()->subDay();
        $streak = 0;

        while ($days->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /**
     * 期限が近い未完了タスク。
     *
     * @return Collection<int, Task>
     */
    private function upcoming(): Collection
    {
        return $this->query()
            ->with('tags')
            ->whereNot('status', TaskStatus::Done)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today()->addWeek())
            ->sorted('due_date')
            ->limit(5)
            ->get();
    }

    /**
     * よく使っているタグ上位 5 件。
     *
     * @return Collection<int, \App\Models\Tag>
     */
    private function topTags(): Collection
    {
        return Auth::user()->tags()
            ->withCount('tasks')
            ->reorder()
            ->orderByDesc('tasks_count')
            ->limit(5)
            ->get()
            ->filter(fn ($tag) => $tag->tasks_count > 0)
            ->values();
    }
}
