<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 既存ユーザーをメールアドレスで招待する。
 * 新規ユーザーの発行はこのフェーズでは扱わない。
 */
class ProjectMemberRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     *
     * 逆順だと、exists:users,email の結果から
     * 「そのメールアドレスが登録済みか」を誰でも確かめられてしまう。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'role' => ['required', Rule::enum(ProjectRole::class)],
        ];
    }

    /**
     * すでに参加しているユーザーは弾く。unique 制約で 500 にする前にここで止める。
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('email')) {
                    return;
                }

                $alreadyJoined = $this->route('project')
                    ->members()
                    ->whereHas('user', fn ($query) => $query->where('email', $this->input('email')))
                    ->exists();

                if ($alreadyJoined) {
                    $validator->errors()->add('email', 'このユーザーはすでに参加しています。');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'メールアドレス', 'role' => '役割'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.exists' => 'このメールアドレスのユーザーは登録されていません。',
        ];
    }

    /**
     * 招待対象のユーザー。
     */
    public function invitee(): User
    {
        return User::where('email', $this->validated()['email'])->firstOrFail();
    }
}
