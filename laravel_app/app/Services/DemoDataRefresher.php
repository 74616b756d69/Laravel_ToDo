<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * デモデータの日付を「今日」に追いつかせる。
 *
 * デモのシーダーは、期限切れ・期限間近・連続達成日数・バーンダウンが
 * それぞれ意味のある見え方になるよう、シードした日を基準に日付を作っている。
 * そのまま日が経つと、期限間近だった課題が次々に期限切れになり、
 * 推移グラフや連続達成も途切れて、デモとして見せたい姿から崩れていく。
 *
 * そこで、最後に揃えた日から経った日数だけ、デモの日付をまとめて後ろへずらす。
 * 全部を同じ日数ずらすので、期限と完了日・スプリント期間の前後関係は保たれる。
 */
class DemoDataRefresher
{
    /** 最後に日付を揃えた日（Y-m-d）を覚えておくキャッシュのキー */
    public const ANCHOR_KEY = 'demo.anchor_date';

    /**
     * ずらす列。キーはテーブル名、値は日付・日時の列。
     * 課題に紐づくものは issue_id、スプリントは project_id で対象を絞る。
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'tasks' => ['due_date', 'completed_at', 'created_at', 'updated_at'],
        'sprints' => ['start_date', 'end_date', 'created_at', 'updated_at'],
        'comments' => ['created_at', 'updated_at', 'edited_at'],
        'activities' => ['created_at'],
    ];

    /**
     * シード直後に呼び、今日を基準日として記録する。
     */
    public static function markFresh(): void
    {
        Cache::forever(self::ANCHOR_KEY, today()->toDateString());
    }

    /**
     * 基準日から経った日数だけ、デモユーザーのプロジェクトの日付をずらす。
     *
     * @return int ずらした日数（何もしなければ 0）
     */
    public function refresh(User $user): int
    {
        // 同時にログインされても二重にずらさないよう、ロックの中で基準日を読み直す
        return Cache::lock('demo.refresh', 10)->block(5, function () use ($user) {
            $projectIds = DB::table('project_members')->where('user_id', $user->id)->pluck('project_id');
            $issueIds = DB::table('tasks')->whereIn('project_id', $projectIds)->pluck('id');

            $anchor = $this->anchor($issueIds->all());
            $days = $anchor === null ? 0 : (int) $anchor->diffInDays(today(), false);

            if ($days > 0) {
                DB::transaction(function () use ($days, $projectIds, $issueIds) {
                    $this->shift('tasks', 'id', $issueIds->all(), $days);
                    $this->shift('sprints', 'project_id', $projectIds->all(), $days);
                    $this->shift('comments', 'issue_id', $issueIds->all(), $days);
                    $this->shift('activities', 'issue_id', $issueIds->all(), $days);
                });
            }

            self::markFresh();

            return max($days, 0);
        });
    }

    /**
     * 基準日。記録が無ければ（この仕組みより前にシードしたデータなど）、
     * 最後に完了した日で代用する。シーダーはシード当日にも必ず完了を作るため。
     *
     * @param  list<int>  $issueIds
     */
    private function anchor(array $issueIds): ?Carbon
    {
        $recorded = Cache::get(self::ANCHOR_KEY);

        if ($recorded !== null) {
            return Carbon::parse($recorded)->startOfDay();
        }

        $latest = DB::table('tasks')->whereIn('id', $issueIds)->max('completed_at');

        return $latest === null ? null : Carbon::parse($latest)->startOfDay();
    }

    /**
     * 1 テーブル分をずらす。
     *
     * SQL の日付関数は MySQL と SQLite で書き方が違うので、PHP 側で計算して書き戻す。
     * デモの件数なら行ごとの更新で十分に速い。元の書式（日付のみ / 日時）は保つ。
     *
     * @param  list<int>  $ids
     */
    private function shift(string $table, string $scope, array $ids, int $days): void
    {
        $columns = self::COLUMNS[$table];

        DB::table($table)
            ->whereIn($scope, $ids)
            ->select(['id', ...$columns])
            ->orderBy('id')
            ->get()
            ->each(function (object $row) use ($table, $columns, $days) {
                $values = collect($columns)
                    ->filter(fn (string $column) => $row->{$column} !== null)
                    ->mapWithKeys(fn (string $column) => [$column => $this->addDays((string) $row->{$column}, $days)]);

                if ($values->isNotEmpty()) {
                    DB::table($table)->where('id', $row->id)->update($values->all());
                }
            });
    }

    private function addDays(string $value, int $days): string
    {
        $format = strlen($value) === 10 ? 'Y-m-d' : 'Y-m-d H:i:s';

        return Carbon::parse($value)->addDays($days)->format($format);
    }
}
