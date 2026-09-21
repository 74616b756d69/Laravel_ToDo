<?php

namespace App\Http\Requests\Project;

use App\Models\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ステータス削除時に、残った課題をどこへ移すかを受け取る。
 *
 * スプリント完了（CompleteSprintRequest）と同じ 2 段構え。
 * 違いは、こちらは移送先を省けないことがある点（課題が残っていれば必須）。
 */
class DeleteStatusRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     *
     * 逆順だと、移送先 ID が同じプロジェクトのものかを
     * 検証エラーの有無から推測できてしまう。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $status = $this->route('status');

        return [
            'destination' => [
                'nullable', 'integer',
                Rule::exists('statuses', 'id')
                    ->where('project_id', $status->project_id)
                    ->whereNot('id', $status->id),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['destination' => '移送先'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'destination.exists' => '移送先には、同じプロジェクトの別のステータスだけを指定できます。',
        ];
    }

    /**
     * 残った課題の移送先。指定が無ければ null（課題が残っていればサービス側で弾かれる）。
     */
    public function destination(): ?Status
    {
        $id = $this->validated()['destination'] ?? null;

        return blank($id) ? null : Status::findOrFail($id);
    }
}
