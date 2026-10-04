<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'role' => $this->whenPivotLoadedAs('membership', 'project_members', fn () => $this->membership->role->value),
            'statuses' => $this->whenLoaded('statuses', fn () => $this->statuses->map(fn ($status) => [
                'id' => $status->id,
                'name' => $status->name,
                'category' => $status->category->value,
            ])),
        ];
    }
}
