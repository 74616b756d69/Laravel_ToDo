<?php

namespace App\Http\Controllers;

use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Project;
use App\Services\SprintService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** 完了数の推移を見る日数 */
    private const TREND_DAYS = 14;

    public function __construct(private readonly SprintService $sprints) {}

    public function index(): View
    {
        return view('dashboard.index', [
            'totals' => $this->totals(),
            'trend' => $this->completionTrend(),
            'byPriority' => $this->openTasksByPriority(),
            'streak' => $this->streak(),
            'upcoming' => $this->upcoming(),
            'topTags' => $this->topTags(),
            // 進行中スプリントがあればバーンダウンを出す。無ければ null
            'burndown' => $this->sprints->burndownFor(Project::personalFor(Auth::user())),
        ]);
    }

    /**
     * 集計の対象。自分が参加しているプロジェクトの課題すべて。
     * サブタスクは親に内包されるものなので、指標には数えない。
     *
     * @return Builder<Issue>
     */
    private function query(): Builder
    {
        return Issue::query()->visibleTo(Auth::user())->topLevel();
    }

    /**
     * 画面上部の KPI。
     *
     * @return array<string, int>
     */
    private function totals(): array
    {
        // ステータス名はプロジェクトごとに違うので、集計はカテゴリ単位で行う
        $counts = $this->query()
            ->join('statuses', 'statuses.id', '=', 'tasks.status_id')
            ->selectRaw('statuses.category as category, count(*) as aggregate')
            ->groupBy('statuses.category')
            ->pluck('aggregate', 'category');

        $done = (int) $counts->get(StatusCategory::Done->value, 0);
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
            ->countBy(fn (Issue $issue) => $issue->completed_at->toDateString());

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
            ->completed(false)
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
            ->map(fn (Issue $issue) => $issue->completed_at->toDateString())
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
     * @return Collection<int, Issue>
     */
    private function upcoming(): Collection
    {
        return $this->query()
            ->with('tags', 'project', 'status')
            ->completed(false)
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
            ->withCount('issues')
            ->reorder()
            ->orderByDesc('issues_count')
            ->limit(5)
            ->get()
            ->filter(fn ($tag) => $tag->issues_count > 0)
            ->values();
    }
}
