<?php

namespace App\Services;

use App\Enums\StatusCategory;
use App\Exceptions\IllegalTransitionException;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
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
    private const DEFAULT_STATUSES = [
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
    private const DEFAULT_TRANSITIONS = [
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
}
