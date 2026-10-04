<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Comment
 */
class CommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'body_text' => $this->body_text,
            'author' => $this->user ? new UserResource($this->user) : null,
            'edited' => $this->wasEdited(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
