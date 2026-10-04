<?php

namespace App\Http\Requests\Api;

use App\Enums\IssueType;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * API からの課題の作成・更新。
 *
 * 更新（PATCH）は送られてきた項目だけを変える。どの項目も sometimes にして、
 * 作成のときだけ title を必須にする。
 */
class IssueApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $issue = $this->route('issue');

        return $issue instanceof Issue
            ? $this->user()->can('update', $issue)
            : $this->user()->can('create', [Issue::class, $this->project()]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $projectId = $this->project()->id;
        $creating = ! $this->route('issue') instanceof Issue;

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'type' => ['sometimes', Rule::enum(IssueType::class)->except([IssueType::Subtask])],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            // ステータスはそのプロジェクトのものだけ。id でも名前でも指定できる
            'status' => ['sometimes'],
            'assignee_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('project_members', 'user_id')->where('project_id', $projectId),
            ],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'story_points' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'estimate_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:60000'],
        ];
    }

    /**
     * ステータスの指定（id か名前）を、そのプロジェクトのステータスに引く。
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->has('status') && $this->status() === null) {
                    $validator->errors()->add('status', 'そのステータスはこのプロジェクトにありません。');
                }
            },
        ];
    }

    public function project(): Project
    {
        $issue = $this->route('issue');

        return $issue instanceof Issue ? $issue->project : $this->route('project');
    }

    public function status(): ?\App\Models\Status
    {
        $value = $this->input('status');

        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return $this->project()->statuses()
            ->where(fn ($query) => is_numeric($value)
                ? $query->whereKey((int) $value)
                : $query->where('name', (string) $value))
            ->first();
    }

    /**
     * そのまま書いてよい項目（fillable の範囲）。
     *
     * @return array<string, mixed>
     */
    public function issueAttributes(): array
    {
        $validated = $this->validated();

        return collect([
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'] ?? null,
            'issue_type' => $validated['type'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'story_points' => $validated['story_points'] ?? null,
            'original_estimate_minutes' => $validated['estimate_minutes'] ?? null,
        ])
            // PATCH では送られてきたものだけ（null を送れば消す、送らなければ触らない）
            ->filter(fn ($value, string $key) => $this->has(match ($key) {
                'issue_type' => 'type',
                'original_estimate_minutes' => 'estimate_minutes',
                default => $key,
            }))
            ->all();
    }
}
