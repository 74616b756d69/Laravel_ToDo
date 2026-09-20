<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * プロジェクトの作成と更新で入力仕様は同じなので 1 クラスに寄せている。
 */
class ProjectRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project === null || $this->user()->can('update', $project);
    }

    /**
     * 課題キーは常に大文字で保存したいので、検証の前に揃えておく。
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('key')) {
            $this->merge(['key' => strtoupper(trim((string) $this->input('key')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'key' => [
                'required', 'string', 'regex:/\A[A-Z]{2,10}\z/',
                // 課題キーは PROJ-123 の接頭辞になるので全体で一意にする
                Rule::unique('projects', 'key')->ignore($this->route('project')),
            ],
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'key' => '課題キー',
            'name' => 'プロジェクト名',
            'description' => '説明',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'key.regex' => '課題キーは大文字の英字 2〜10 文字で入力してください。',
        ];
    }
}
