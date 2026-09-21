<?php

namespace App\Http\Requests\Task;

use App\Enums\IssueType;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use App\Support\ProjectContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 課題の作成と更新で入力仕様は同じなので 1 クラスに寄せている。
 */
class TaskRequest extends FormRequest
{
    /**
     * 認可を先に済ませる。
     *
     * ステータスの検証はプロジェクトに依存するので、権限の無い相手に
     * 「そのステータスは無効」と返すと、他プロジェクトの事情が漏れる。
     * 触れない相手にはバリデーションより前に 403 を返す。
     */
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task === null
            ? $this->user()->can('create', [Issue::class, $this->project()])
            : $this->user()->can('update', $task);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'content' => ['nullable', 'string', 'max:2000'],
            // ステータスはプロジェクトごとに違うので、そのプロジェクトのものだけ受け付ける
            'status' => [
                'required', 'integer',
                Rule::exists('statuses', 'id')->where('project_id', $this->project()->id),
            ],
            // 課題タイプと担当者は欄が無い経路（クイック追加など）もあるので sometimes。
            // 送られてきたときだけ検証し、無ければ既存の値・既定値に任せる
            'issue_type' => ['sometimes', Rule::enum(IssueType::class)],
            // 担当者はそのプロジェクトのメンバーだけ。空文字は未割り当て
            'assignee' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('project_members', 'user_id')->where('project_id', $this->project()->id),
            ],
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
            'issue_type' => '課題タイプ',
            'assignee' => '担当者',
            'priority' => '優先度',
            'due_date' => '期限',
            'tags' => 'タグ',
        ];
    }

    /**
     * この課題が属する（属することになる）プロジェクト。
     * 更新なら課題のプロジェクト、新規ならいま見ているプロジェクト。
     */
    public function project(): Project
    {
        return $this->route('task')?->project
            ?? app(ProjectContext::class)->current($this->user());
    }

    /**
     * 指定されたステータス。
     */
    public function status(): Status
    {
        return Status::findOrFail((int) $this->validated()['status']);
    }

    /**
     * 担当者の欄が送られてきたか。
     *
     * 更新では「空で送られた（＝未割り当てにしたい）」と
     * 「そもそも欄が無い」を区別する必要がある。
     */
    public function hasAssignee(): bool
    {
        return array_key_exists('assignee', $this->validated());
    }

    /**
     * 指定された担当者。未割り当てなら null。
     */
    public function assignee(): ?User
    {
        $id = $this->validated()['assignee'] ?? null;

        return blank($id) ? null : User::findOrFail($id);
    }

    /**
     * ステータスと担当者以外の属性。
     *
     * ステータスをここに含めないのは、遷移の可否を検査せずに書き換えてしまわないため。
     * 状態の変更は WorkflowService::transition() が受け持つ。
     * 担当者も同じ理由で外す（IssueAssignmentService が受け持つ）。
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $validated = $this->validated();

        unset($validated['tags'], $validated['status'], $validated['assignee']);

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
