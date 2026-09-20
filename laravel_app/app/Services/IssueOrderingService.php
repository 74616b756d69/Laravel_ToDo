<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ボードとバックログの並び順。
 *
 * どちらもドラッグのたびに「移動先の並び全体」を受け取るので、
 * 受け取った ID のうち自分が書き込めるものだけを、送られた順に並べ直す。
 */
class IssueOrderingService
{
    /**
     * 受け取った順序で position を振り直す。
     *
     * 1 行ずつ UPDATE すると、レーンのカード枚数だけクエリが飛ぶ。
     * CASE 式 1 本にまとめて、ドラッグ 1 回あたり 1 クエリで済ませる。
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, int> 実際に並べ替えた ID（送られた順）
     */
    public function apply(User $user, array $ids): Collection
    {
        $ordered = $this->writableInOrder($user, $ids);

        if ($ordered->isEmpty()) {
            return $ordered;
        }

        $cases = $ordered
            ->map(fn (int $id, int $position) => "when {$id} then {$position}")
            ->implode(' ');

        Issue::whereKey($ordered->all())->update([
            'position' => DB::raw("case id {$cases} end"),
        ]);

        return $ordered;
    }

    /**
     * 受け取った順序を保ったまま、自分が書き込めるものの ID だけを取り出す。
     *
     * 参加していないプロジェクトの課題が紛れ込んでも、ここで落ちる。
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, int>
     */
    private function writableInOrder(User $user, array $ids): Collection
    {
        $writable = Issue::query()
            ->visibleTo($user)
            ->whereKey($ids)
            ->with('project')
            ->get()
            ->filter(fn (Issue $issue) => $user->can('update', $issue))
            ->pluck('id')
            ->flip();

        return collect($ids)->filter(fn (int $id) => $writable->has($id))->values();
    }
}
