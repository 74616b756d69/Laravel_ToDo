<?php

namespace App\Http\Controllers\Task;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Support\ParsedQuickAdd;
use App\Support\QuickAddParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
                // ボードの各レーンから追加するときは、そのレーンのステータスが入る
                'status' => ['nullable', Rule::enum(TaskStatus::class)],
            ],
            attributes: ['quick' => '入力', 'status' => 'ステータス'],
        );

        $status = TaskStatus::tryFrom($validated['status'] ?? '') ?? TaskStatus::Todo;

        $parsed = (new QuickAddParser(Auth::user()->tags))->parse($validated['quick']);

        // 記法だけでタイトルが残らなかった場合は作らずに知らせる
        if (! $parsed->hasTitle()) {
            return back()->withInput()->withErrors([
                'quick' => 'タスクの内容を入力してください。',
            ]);
        }

        $task = Auth::user()->tasks()->create([
            'title' => $parsed->title,
            'status' => $status,
            'priority' => $parsed->priority,
            'due_date' => $parsed->dueDate,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
            // ボードでは追加したレーンの末尾に置く
            'position' => $this->nextPosition($status),
        ]);

        $task->tags()->sync($parsed->tagIds);

        $fromBoard = filled($validated['status'] ?? null);

        $redirect = back()->with('status', $this->summarize($task, $parsed, explicitStatus: $fromBoard));

        // ボードから追加したカードはレーンの末尾に入るため、その位置まで送る
        return $fromBoard ? $redirect->withFragment("task-{$task->id}") : $redirect;
    }

    /**
     * 指定レーンの末尾に来る position を返す。
     */
    private function nextPosition(TaskStatus $status): int
    {
        return (int) Auth::user()->tasks()->where('status', $status)->max('position') + 1;
    }

    /**
     * 何をどう解釈したかを伝える。意図と違う解釈にすぐ気づけるようにするため。
     */
    private function summarize(Task $task, ParsedQuickAdd $parsed, bool $explicitStatus = false): string
    {
        $details = collect([
            $explicitStatus ? $task->status->label() : null,
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
