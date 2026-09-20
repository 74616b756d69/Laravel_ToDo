<?php

namespace App\Services;

use App\Enums\SprintState;
use App\Exceptions\SprintException;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * スプリントの番人。
 *
 * 状態の変更と課題の出し入れは必ずここを通す。
 * Sprint::update(['state' => ...]) を直接呼ぶと、
 * 「同時に active は 1 つだけ」も active_marker の整合も守られない。
 */
class SprintService
{
    /**
     * 未開始のスプリントを作る。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Project $project, array $attributes): Sprint
    {
        $sprint = $project->sprints()->make($attributes);

        $sprint->state = SprintState::Future;
        $sprint->active_marker = null;
        $sprint->save();

        return $sprint;
    }

    /**
     * スプリントを開始する（future → active）。
     *
     * 同時に active にできるのはプロジェクトごとに 1 つだけ。
     * アプリ側で先に確認しつつ、競合した場合は DB の
     * unique(project_id, active_marker) が最後の砦になる。
     */
    public function start(Sprint $sprint): Sprint
    {
        if (! $sprint->isFuture()) {
            throw SprintException::notStartable($sprint->name, $sprint->state->label());
        }

        try {
            return DB::transaction(function () use ($sprint) {
                $active = $this->activeFor($sprint->project);

                if ($active !== null) {
                    throw SprintException::alreadyActive($active->name);
                }

                $sprint->forceFill([
                    'state' => SprintState::Active,
                    // この 1 が unique 制約の鍵。active のときだけ値が入る
                    'active_marker' => 1,
                    'start_date' => $sprint->start_date ?? today(),
                ])->save();

                return $sprint;
            });
        } catch (UniqueConstraintViolationException) {
            // competing request が一瞬先に開始した
            $active = $this->activeFor($sprint->project->fresh());

            throw SprintException::alreadyActive($active?->name ?? '別のスプリント');
        }
    }

    /**
     * スプリントを完了する（active → closed）。
     *
     * 完了していない課題は捨てずに、指定された移送先へ送る。
     * $destination が null ならバックログへ戻す。
     *
     * @return int 移送した課題の件数
     */
    public function complete(Sprint $sprint, ?Sprint $destination = null): int
    {
        if (! $sprint->isActive()) {
            throw SprintException::notCompletable($sprint->name, $sprint->state->label());
        }

        $this->assertValidDestination($sprint, $destination);

        return DB::transaction(function () use ($sprint, $destination) {
            $moved = $this->carryOverIncomplete($sprint, $destination);

            $sprint->forceFill([
                'state' => SprintState::Closed,
                // active を降りるので marker を外す。次のスプリントが開始できるようになる
                'active_marker' => null,
                'end_date' => $sprint->end_date ?? today(),
            ])->save();

            return $moved;
        });
    }

    /**
     * 未完了の課題を移送先へ送る。完了済みはスプリントに残して実績にする。
     */
    private function carryOverIncomplete(Sprint $sprint, ?Sprint $destination): int
    {
        $incomplete = $sprint->issues()->completed(false)->get();

        // 1 件ずつモデル経由で動かす。クエリビルダの一括 update は
        // モデルイベントが飛ばないので、履歴に残らなくなる
        $incomplete->each(fn (Issue $issue) => $this->assign($issue, $destination));

        return $incomplete->count();
    }

    /**
     * 移送先として妥当か。同じプロジェクトの未開始スプリントか、バックログ（null）だけ。
     */
    private function assertValidDestination(Sprint $sprint, ?Sprint $destination): void
    {
        if ($destination === null) {
            return;
        }

        $sameProject = $destination->project_id === $sprint->project_id;
        $isFuture = $destination->isFuture();

        if (! $sameProject || ! $isFuture || $destination->is($sprint)) {
            throw SprintException::invalidDestination();
        }
    }

    /**
     * 課題をスプリントへ入れる / バックログへ戻す。
     *
     * 完了したスプリントへは入れられない（実績が後から変わってしまうため）。
     */
    public function assign(Issue $issue, ?Sprint $sprint): Issue
    {
        if ($sprint !== null && ($sprint->project_id !== $issue->project_id || $sprint->isClosed())) {
            throw SprintException::invalidDestination();
        }

        $issue->forceFill(['sprint_id' => $sprint?->id])->save();

        return $issue;
    }

    /**
     * そのプロジェクトで進行中のスプリント。無ければ null。
     */
    public function activeFor(Project $project): ?Sprint
    {
        return $project->sprints()->state(SprintState::Active)->first();
    }

    /**
     * 完了時の移送先として選べるスプリント（未開始のもの）。
     *
     * @return Collection<int, Sprint>
     */
    public function destinationsFor(Sprint $sprint): Collection
    {
        return $sprint->project->sprints()
            ->state(SprintState::Future)
            ->whereKeyNot($sprint->getKey())
            ->ordered()
            ->get();
    }

    /**
     * 進行中スプリントのバーンダウン。
     *
     * 日ごとのスナップショットを持っていないので、完了時刻から逆算する。
     * 「その日の終わりまでに完了した分」を総量から引いた残りを並べる。
     * 未来の日付は線を引かない（null）。
     *
     * @return array{
     *     sprint: Sprint,
     *     total: int,
     *     unit: string,
     *     days: Collection<int, array{date: \Illuminate\Support\Carbon, remaining: ?int, ideal: float}>
     * }|null
     */
    public function burndownFor(Project $project): ?array
    {
        $sprint = $this->activeFor($project);

        if ($sprint === null || $sprint->start_date === null || $sprint->end_date === null) {
            return null;
        }

        $issues = $sprint->issues()->with('status')->get();

        if ($issues->isEmpty()) {
            return null;
        }

        // ストーリーポイントが 1 つも入っていないなら件数で数える。
        // 見積り前のスプリントでもグラフが成立するようにするため
        $hasPoints = $issues->contains(fn (Issue $issue) => $issue->story_points !== null);
        $weight = fn (Issue $issue) => $hasPoints ? (int) ($issue->story_points ?? 0) : 1;

        $total = $issues->sum($weight);
        $span = max((int) $sprint->start_date->diffInDays($sprint->end_date), 1);

        $days = collect(range(0, $span))->map(function (int $offset) use ($sprint, $issues, $weight, $total, $span) {
            $date = $sprint->start_date->copy()->addDays($offset);

            $burned = $issues
                ->filter(fn (Issue $issue) => $issue->isCompleted()
                    && $issue->completed_at !== null
                    && $issue->completed_at->lessThanOrEqualTo($date->copy()->endOfDay()))
                ->sum($weight);

            return [
                'date' => $date,
                // 未来はまだ実績が無いので線を引かない
                'remaining' => $date->isFuture() ? null : $total - $burned,
                'ideal' => round($total - ($total * $offset / $span), 2),
            ];
        });

        return [
            'sprint' => $sprint,
            'total' => $total,
            'unit' => $hasPoints ? 'ポイント' : '件',
            'days' => $days,
        ];
    }
}
