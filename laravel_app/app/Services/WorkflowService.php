<?php

namespace App\Services;

use App\Enums\StatusCategory;
use App\Exceptions\IllegalTransitionException;
use App\Exceptions\WorkflowException;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
use App\Models\Transition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ワークフローの番人。
 *
 * 課題のステータス変更は必ずここを通す。モデルに書き込みを持たせないのは、
 * 「遷移が許されているか」の判断を 1 箇所に閉じ込めるため。
 * Issue::update(['status_id' => ...]) のような抜け道を作らないこと。
 */
class WorkflowService
{
    /**
     * 新しいプロジェクトに入れる既定のワークフロー。
     *
     * 遷移をあえて全許可にしていないのは、ワークフローが設定できること自体が
     * このフェーズの主題だから。一方で「一覧から 1 クリックで完了」は
     * 移行前からある動線なので、To Do → Done は通してある。
     */
    public const DEFAULT_STATUSES = [
        ['name' => 'To Do', 'category' => StatusCategory::Todo],
        ['name' => 'In Progress', 'category' => StatusCategory::InProgress],
        ['name' => 'In Review', 'category' => StatusCategory::InProgress],
        ['name' => 'Done', 'category' => StatusCategory::Done],
    ];

    /**
     * 既定の遷移。null は「どのステータスからでも」。
     *
     * 禁止されるのは To Do → In Review と Done → In Review の 2 本。
     * 「着手していないものをレビューに出す」「完了したものをレビューに戻す」を防ぐ。
     */
    public const DEFAULT_TRANSITIONS = [
        // いつでも差し戻せる（global transition）
        [null, 'To Do'],
        ['To Do', 'In Progress'],
        ['To Do', 'Done'],
        ['In Progress', 'In Review'],
        ['In Progress', 'Done'],
        ['In Review', 'In Progress'],
        ['In Review', 'Done'],
        ['Done', 'In Progress'],
    ];

    /**
     * 既定のワークフローをプロジェクトに入れる。
     * すでにステータスがあれば何もしない（冪等）。
     */
    public function installDefaults(Project $project): void
    {
        // フェーズ 2 の移行コマンドは、statuses がまだ無い時点でプロジェクトを作る。
        // その段階では何もせず、あとから workflows:install が入れる。
        if (! DB::getSchemaBuilder()->hasTable('statuses')) {
            return;
        }

        if ($project->statuses()->exists()) {
            return;
        }

        DB::transaction(function () use ($project) {
            $statuses = collect(self::DEFAULT_STATUSES)
                ->mapWithKeys(fn (array $row, int $index) => [
                    $row['name'] => $project->statuses()->create([
                        'name' => $row['name'],
                        'category' => $row['category'],
                        'position' => $index,
                    ]),
                ]);

            foreach (self::DEFAULT_TRANSITIONS as [$from, $to]) {
                $project->transitions()->create([
                    'from_status_id' => $from === null ? null : $statuses[$from]->id,
                    'to_status_id' => $statuses[$to]->id,
                ]);
            }
        });
    }

    /**
     * その課題を to へ移せるか。
     *
     * 同じステータスへの「移動」は遷移ではないので常に true
     * （ボードでのレーン内の並べ替えがこれにあたる）。
     */
    public function allows(Issue $issue, Status $to): bool
    {
        if ($issue->status_id === $to->id) {
            return true;
        }

        // 別プロジェクトのステータスへは移せない
        if ($issue->project_id !== $to->project_id) {
            return false;
        }

        // 遷移が 1 本も定義されていないプロジェクトは全許可とする。
        // ワークフロー導入前に作られたプロジェクトを動かなくしないための逃げ道
        if (! $issue->project->transitions()->exists()) {
            return true;
        }

        return $issue->project->transitions()
            ->where('to_status_id', $to->id)
            ->where(fn ($query) => $query
                ->whereNull('from_status_id')
                ->orWhere('from_status_id', $issue->status_id))
            ->exists();
    }

    /**
     * 許可されていなければ例外を投げる。
     */
    public function assertAllowed(Issue $issue, Status $to): void
    {
        if (! $this->allows($issue, $to)) {
            throw new IllegalTransitionException($issue->status, $to);
        }
    }

    /**
     * ステータスを変更する。許可されていなければ例外。
     *
     * 完了時刻の面倒もここで見る。カテゴリが done のステータスに入ったら打刻し、
     * 出たら消す。名前ではなくカテゴリで判断するので、
     * 「Done」を「リリース済み」に改名しても壊れない。
     */
    public function transition(Issue $issue, Status $to): Issue
    {
        $this->assertAllowed($issue, $to);

        if ($issue->status_id === $to->id) {
            return $issue;
        }

        $issue->forceFill([
            'status_id' => $to->id,
            'completed_at' => $to->isDone() ? ($issue->completed_at ?? now()) : null,
        ])->save();

        return $issue->setRelation('status', $to);
    }

    /**
     * いまこの課題が移れるステータスの一覧（現在地を除く）。
     * 詳細画面の遷移ボタンと、ボードのドロップ可否表示に使う。
     *
     * @return Collection<int, Status>
     */
    public function availableFor(Issue $issue): Collection
    {
        return $issue->project->statuses()
            ->ordered()
            ->get()
            ->filter(fn (Status $status) => $status->id !== $issue->status_id)
            ->filter(fn (Status $status) => $this->allows($issue, $status))
            ->values();
    }

    /**
     * 完了 / 未完了を切り替える。
     *
     * 一覧のチェックボックスとサブタスクのトグルから呼ばれる。
     * 完了にするときは done カテゴリの先頭、戻すときは初期ステータスへ。
     * どちらもワークフローの検査を通るので、禁止されていれば例外になる。
     */
    public function toggleCompletion(Issue $issue): Issue
    {
        $target = $issue->isCompleted()
            ? $issue->project->initialStatus()
            : $issue->project->doneStatus();

        return $this->transition($issue, $target);
    }

    // --- ワークフローの編集 ----------------------------------------------------

    /**
     * ステータスを 1 つ足す。並びは末尾（既存のレーンの順番を動かさないため）。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addStatus(Project $project, array $attributes): Status
    {
        return $project->statuses()->create([
            ...$attributes,
            'position' => (int) $project->statuses()->max('position') + 1,
        ]);
    }

    /**
     * 名前とカテゴリを変える。
     *
     * カテゴリを done から動かすときは、ほかに done が残るかを必ず確かめる。
     * 残らないと doneStatus() の firstOrFail が落ちて、完了トグルも分析も止まる。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateStatus(Status $status, array $attributes): Status
    {
        $category = $attributes['category'] instanceof StatusCategory
            ? $attributes['category']
            : StatusCategory::from((string) $attributes['category']);

        if ($status->isDone() && ! $category->isDone() && ! $this->hasOtherDoneStatus($status)) {
            throw WorkflowException::lastDoneStatus($status->name);
        }

        $status->update($attributes);

        return $status;
    }

    /**
     * レーンを 1 つ前後に動かす。端ならなにもしない。
     *
     * 入れ替えではなく全体に 0 から振り直すのは、position が
     * 重複していても（移行やシーダー由来で起こりうる）確実に並びが変わるようにするため。
     */
    public function moveStatus(Status $status, string $direction): void
    {
        $ordered = $status->project->statuses()->get()->values();
        $index = $ordered->search(fn (Status $candidate) => $candidate->is($status));
        $target = $index + ($direction === 'up' ? -1 : 1);

        if ($index === false || $target < 0 || $target >= $ordered->count()) {
            return;
        }

        $reordered = $ordered->all();
        [$reordered[$index], $reordered[$target]] = [$reordered[$target], $reordered[$index]];

        DB::transaction(function () use ($reordered) {
            foreach ($reordered as $position => $status) {
                $status->update(['position' => $position]);
            }
        });
    }

    /**
     * ステータスを消す。残っている課題は移送先へ移す。
     *
     * 移送はワークフローの遷移ではない（出発点そのものが無くなるので、
     * 遷移が定義されていなくても進めるほかない）。それでも 1 件ずつモデル経由で
     * 保存するのは、IssueObserver に履歴を残させるため。
     *
     * 消せる件数が多い操作なので、前提を破る削除はここで必ず止める。
     *
     * @return int 移送した課題の件数
     */
    public function deleteStatus(Status $status, ?Status $destination = null): int
    {
        $this->assertRemovable($status);

        // ソフトデリート済みの課題も status_id を握っている。
        // tasks.status_id は restrict なので、残すと削除そのものが失敗する
        $issues = Issue::withTrashed()->where('status_id', $status->id);
        $remaining = (clone $issues)->count();

        if ($remaining > 0) {
            if ($destination === null
                || $destination->project_id !== $status->project_id
                || $destination->is($status)) {
                throw $destination === null
                    ? WorkflowException::destinationRequired($status->name, $remaining)
                    : WorkflowException::invalidDestination();
            }
        }

        return DB::transaction(function () use ($issues, $remaining, $destination, $status) {
            if ($remaining > 0) {
                $issues->get()->each(fn (Issue $issue) => $issue->forceFill([
                    'status_id' => $destination->id,
                    'completed_at' => $destination->isDone() ? ($issue->completed_at ?? now()) : null,
                ])->save());
            }

            // このステータスを指す遷移は外部キーの cascade で一緒に消える
            $status->delete();

            return $remaining;
        });
    }

    /**
     * 遷移を 1 本足す。from が null なら「どの状態からでも」。
     */
    public function addTransition(Project $project, ?Status $from, Status $to): Transition
    {
        if ($from !== null && $from->is($to)) {
            throw WorkflowException::pointlessTransition();
        }

        $exists = $project->transitions()
            ->where('to_status_id', $to->id)
            ->where(fn ($query) => $from === null
                ? $query->whereNull('from_status_id')
                : $query->where('from_status_id', $from->id))
            ->exists();

        if ($exists) {
            throw WorkflowException::duplicatedTransition();
        }

        return $project->transitions()->create([
            'from_status_id' => $from?->id,
            'to_status_id' => $to->id,
        ]);
    }

    /**
     * 消してよいステータスか。
     *
     * プロジェクトは必ず「ステータス 1 つ以上」と「done 1 つ以上」を保つ。
     * initialStatus() / doneStatus() が firstOrFail なので、
     * どちらかを割ると全画面が落ちる。
     */
    private function assertRemovable(Status $status): void
    {
        if ($status->project->statuses()->count() <= 1) {
            throw WorkflowException::lastStatus($status->name);
        }

        if ($status->isDone() && ! $this->hasOtherDoneStatus($status)) {
            throw WorkflowException::lastDoneStatus($status->name);
        }
    }

    /**
     * 自分以外に done カテゴリのステータスがあるか。
     */
    private function hasOtherDoneStatus(Status $status): bool
    {
        return $status->project->statuses()
            ->where('category', StatusCategory::Done)
            ->whereKeyNot($status->getKey())
            ->exists();
    }
}
