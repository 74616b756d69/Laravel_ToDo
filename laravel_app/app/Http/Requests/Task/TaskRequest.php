<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * タスクの作成と更新で入力仕様は同じなので 1 クラスに寄せている。
 */
class TaskRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'content' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            'tags' => ['array'],
            // 自分が作ったタグ以外は指定できない
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('user_id', Auth::id()),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'content' => '内容',
            'status' => 'ステータス',
            'priority' => '優先度',
            'due_date' => '期限',
            'tags' => 'タグ',
        ];
    }

    /**
     * 完了に切り替わったタイミングで completed_at を打刻する。
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $validated = $this->validated();
        unset($validated['tags']);

        $isDone = $validated['status'] === TaskStatus::Done->value;

        $validated['completed_at'] = $isDone
            ? ($this->route('task')?->completed_at ?? now())
            : null;

        return $validated;
    }

    /**
     * 付け替えるタグの ID 一覧。
     *
     * @return array<int, int>
     */
    public function tagIds(): array
    {
        return array_map('intval', $this->validated()['tags'] ?? []);
    }
}
