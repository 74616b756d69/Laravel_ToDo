<?php

namespace App\Http\Requests\Project;

use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ワークフローの矢印 1 本。
 */
class TransitionRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $projectId = $this->route('project')->id;

        return [
            // 空なら「どの状態からでも」（global transition）
            'from' => [
                'nullable', 'integer',
                Rule::exists('statuses', 'id')->where('project_id', $projectId),
            ],
            'to' => [
                'required', 'integer',
                Rule::exists('statuses', 'id')->where('project_id', $projectId),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'from' => '変更前のステータス',
            'to' => '変更後のステータス',
        ];
    }

    /** 変更前のステータス。null は「どの状態からでも」。 */
    public function from(): ?Status
    {
        $id = $this->validated()['from'] ?? null;

        return blank($id) ? null : Status::findOrFail($id);
    }

    public function to(): Status
    {
        return Status::findOrFail($this->validated()['to']);
    }
}
