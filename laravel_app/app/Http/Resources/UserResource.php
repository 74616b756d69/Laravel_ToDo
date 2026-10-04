<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 人。メールアドレスは本人（/me）のときだけ出す。
 * 同じプロジェクトの人どうしでも、API でアドレスを一覧できる必要はない。
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($request->user()?->is($this->resource), $this->email),
        ];
    }
}
