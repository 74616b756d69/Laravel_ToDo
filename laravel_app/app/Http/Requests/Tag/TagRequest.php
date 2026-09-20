<?php

namespace App\Http\Requests\Tag;

use App\Enums\TagColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:30',
                // 同じユーザー内で名前の重複を許さない（編集中の自分自身は除く）
                Rule::unique('tags', 'name')
                    ->where('user_id', Auth::id())
                    ->ignore($this->route('tag')),
            ],
            'color' => ['required', Rule::enum(TagColor::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'タグ名', 'color' => '色'];
    }
}
