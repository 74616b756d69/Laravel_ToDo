<?php

namespace App\Http\Requests\Sprint;

use App\Enums\SprintState;
use App\Models\Sprint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * スプリント完了時に、残った課題をどこへ送るかを受け取る。
 */
class CompleteSprintRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     *
     * 逆順だと、移送先 ID が有効な未開始スプリントかどうかを
     * 検証エラーの有無から推測できてしまう。
     */
    public function authorize(): bool
    {
        return $this->user()->can('transition', $this->route('sprint'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $sprint = $this->route('sprint');

        return [
            // 空なら「バックログへ戻す」。値があれば同じプロジェクトの未開始スプリント
            'destination' => [
                'nullable', 'integer',
                Rule::exists('sprints', 'id')
                    ->where('project_id', $sprint->project_id)
                    ->where('state', SprintState::Future->value)
                    ->whereNot('id', $sprint->id),
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
            'destination.exists' => '移送先には、同じプロジェクトの未開始スプリントだけを指定できます。',
        ];
    }

    /**
     * 残った課題の移送先。null はバックログ。
     */
    public function destination(): ?Sprint
    {
        $id = $this->validated()['destination'] ?? null;

        return $id === null ? null : Sprint::findOrFail($id);
    }
}
