<?php

namespace App\Http\Requests\Project;

use App\Enums\StatusCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ワークフローのステータス 1 行。追加と更新で入力仕様は同じ。
 */
class StatusRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     * ワークフローを触れるのは管理者だけ（ProjectPolicy::update）。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:40',
                // レーン名が重複すると、どちらのレーンか見分けられなくなる
                Rule::unique('statuses', 'name')
                    ->where('project_id', $this->route('project')->id)
                    ->ignore($this->route('status')),
            ],
            'category' => ['required', Rule::enum(StatusCategory::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'ステータス名',
            'category' => 'カテゴリ',
        ];
    }
}
