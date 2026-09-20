<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function index(): View
    {
        $tasks = Auth::user()->tasks()
            ->with('tags')
            ->withCount([
                'subtasks',
                'subtasks as done_subtasks_count' => fn ($query) => $query->where('is_done', true),
            ])
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();

        return view('board.index', [
            // ステータスごとにレーン分けする。空のレーンも必ず用意する
            'lanes' => collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $status) => [
                $status->value => [
                    'status' => $status,
                    'tasks' => $tasks->where('status', $status)->values(),
                ],
            ]),
        ]);
    }

    /**
     * ドラッグ＆ドロップの結果を保存する。
     * 移動先レーンの並び順をまとめて受け取り、position を振り直す。
     */
    public function move(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $status = TaskStatus::from($validated['status']);

        // 送られてきた ID のうち、自分のタスクだけを対象にする
        $ownedIds = $this->ownedIdsInOrder($validated['ids']);

        DB::transaction(function () use ($task, $status, $ownedIds) {
            if ($task->status !== $status) {
                $task->forceFill([
                    'status' => $status,
                    'completed_at' => $status === TaskStatus::Done ? ($task->completed_at ?? now()) : null,
                ])->save();
            }

            foreach ($ownedIds as $position => $id) {
                Task::whereKey($id)->update(['position' => $position]);
            }
        });

        return response()->json([
            'status' => $status->value,
            'completed_at' => $task->fresh()->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * 受け取った順序を保ったまま、自分のタスクの ID だけを取り出す。
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, int>
     */
    private function ownedIdsInOrder(array $ids): Collection
    {
        $owned = Auth::user()->tasks()->whereKey($ids)->pluck('id')->flip();

        return collect($ids)->filter(fn (int $id) => $owned->has($id))->values();
    }
}
