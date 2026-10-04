<?php

namespace App\Http\Controllers\Issue;

use App\Enums\ActivityField;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Worklog;
use App\Support\Duration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 作業時間の記録と削除。記録はいつも「ログイン中の人の時間」。
 *
 * 書き直しは持たない。直したいときは消して記録し直す（履歴に両方が残る）。
 */
class WorklogController extends Controller
{
    public function store(Request $request, Issue $task): RedirectResponse
    {
        $this->authorize('create', [Worklog::class, $task]);

        $validated = $request->validateWithBag('worklog', [
            'time' => ['required', 'string', 'max:20'],
            // 先の日付は受けない（予定ではなく実績なので）
            'worked_on' => ['required', 'date', 'before_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:255'],
        ], attributes: ['time' => '作業時間', 'worked_on' => '作業日', 'comment' => 'メモ']);

        $minutes = Duration::parse($validated['time']);

        if ($minutes === null || $minutes > Duration::MAX_MINUTES) {
            throw ValidationException::withMessages([
                'time' => $minutes === null
                    ? '作業時間は「1h30m」「45m」「2h」のように書いてください。'
                    : '1 回に記録できるのは 24 時間までです。',
            ])->errorBag('worklog');
        }

        $worklog = DB::transaction(function () use ($task, $request, $validated, $minutes) {
            $worklog = $task->worklogs()->make([
                'minutes' => $minutes,
                'worked_on' => $validated['worked_on'],
                'comment' => $validated['comment'] ?? null,
            ])->forceFill(['user_id' => $request->user()->id]);
            $worklog->save();

            $this->record($task, $request->user()->id, null, $this->describe($worklog));

            return $worklog;
        });

        return redirect()->route('tasks.show', $task)
            ->with('status', "作業時間 {$worklog->duration()} を記録しました。");
    }

    public function destroy(Request $request, Issue $task, Worklog $worklog): RedirectResponse
    {
        // URL の組み合わせを差し替えて、他の課題の記録を消されないようにする
        abort_unless($worklog->issue_id === $task->id, 404);
        $this->authorize('delete', $worklog);

        DB::transaction(function () use ($task, $request, $worklog) {
            $worklog->delete();

            $this->record($task, $request->user()->id, $this->describe($worklog), null);
        });

        return redirect()->route('tasks.show', $task)
            ->with('status', "作業時間 {$worklog->duration()} の記録を削除しました。");
    }

    private function describe(Worklog $worklog): string
    {
        return "{$worklog->duration()}（{$worklog->worked_on->isoFormat('M/D')}）";
    }

    private function record(Issue $task, int $userId, ?string $old, ?string $new): void
    {
        Activity::create([
            'issue_id' => $task->id,
            'user_id' => $userId,
            'field' => ActivityField::Worklog,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
