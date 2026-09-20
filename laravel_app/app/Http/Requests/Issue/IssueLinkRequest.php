<?php

namespace App\Http\Requests\Issue;

use App\Enums\IssueLinkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueLinkRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(IssueLinkType::class)],
            // 課題キー（PROJ-12）か詳細画面の URL
            'target' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['type' => '関連の種類', 'target' => '課題'];
    }

    public function type(): IssueLinkType
    {
        return IssueLinkType::from($this->validated()['type']);
    }
}
