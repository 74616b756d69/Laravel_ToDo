<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Status;
use App\Support\ParsedQuickAdd;
use App\Support\ProjectContext;
use App\Support\QuickAddParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 1 行の入力からタスクを作る。
 */
class QuickAddController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'quick' => ['required', 'string', 'max:200'],
                // ボードの各レーンから追加するときは、そのレーンのステータス ID が入る
                'status' => ['nullable', 'integer'],
            ],
            attributes: ['quick' => '入力', 'status' => 'ステータス'],
        );

        // 作成先はヘッダーで選ばれているプロジェクト
        $project = app(ProjectContext::class)->current(Auth::user());

        // レーン指定が無ければ初期ステータス。他プロジェクトの ID は弾く
        $status = blank($validated['status'] ?? null)
            ? $project->initialStatus()
            : $project->statuses()->findOrFail($validated['status']);

        $parsed = (new QuickAddParser(Auth::user()->tags))->parse($validated['quick']);

        // 記法だけでタイトルが残らなかった場合は作らずに知らせる
        if (! $parsed->hasTitle()) {
            return back()->withInput()->withErrors([
                'quick' => 'タスクの内容を入力してください。',
            ]);
        }

        $this->authorize('create', [Issue::class, $project]);

        $task = $project->createIssue([
            'title' => $parsed->title,
            'status_id' => $status->id,
            'priority' => $parsed->priority,
            'due_date' => $parsed->dueDate,
            'completed_at' => $status->isDone() ? now() : null,
            'reporter_id' => Auth::id(),
            'assignee_id' => Auth::id(),
            // ボードでは追加したレーンの末尾に置く
            'position' => $this->nextPosition($status),
        ]);

        $task->tags()->sync($parsed->tagIds);

        $fromBoard = filled($validated['status'] ?? null);

        $redirect = back()->with('status', $this->summarize($task, $status, $parsed, explicitStatus: $fromBoard));

        // ボードから追加したカードはレーンの末尾に入るため、その位置まで送る
        return $fromBoard ? $redirect->withFragment("task-{$task->id}") : $redirect;
    }

    /**
     * 指定レーンの末尾に来る position を返す。
     * ボードは参加プロジェクトの課題をまとめて並べるので、その範囲で見る。
     */
    private function nextPosition(Status $status): int
    {
        return (int) Issue::query()
            ->visibleTo(Auth::user())
            ->topLevel()
            ->where('status_id', $status->id)
            ->max('position') + 1;
    }

    /**
     * 何をどう解釈したかを伝える。意図と違う解釈にすぐ気づけるようにするため。
     */
    private function summarize(Issue $task, Status $status, ParsedQuickAdd $parsed, bool $explicitStatus = false): string
    {
        $details = collect([
            $explicitStatus ? $status->name : null,
            $task->due_date ? '期限 '.$task->due_date->isoFormat('M/D(ddd)') : null,
            '優先度'.$task->priority->label(),
            $parsed->tagIds ? 'タグ '.$task->tags->pluck('name')->implode('・') : null,
        ])->filter()->implode(' / ');

        $message = "「{$task->title}」を追加しました（{$details}）。";

        if ($parsed->unknownTags) {
            $message .= ' 未登録のタグは無視しました：'.implode('・', $parsed->unknownTags);
        }

        return $message;
    }
}
