<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 課題。呼ぶ側で project / status / assignee / reporter / tags を読んでおくこと。
 *
 * @mixin \App\Models\Issue
 */
class IssueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key(),
            'url' => route('tasks.show', $this->resource),
            'project' => ['key' => $this->project->key, 'name' => $this->project->name],
            'title' => $this->title,
            // HTML（サニタイズ済み）と、検索や表示に使いやすい平文の両方を返す
            'content' => $this->content,
            'content_text' => $this->content_text,
            'type' => $this->issue_type->value,
            'priority' => $this->priority->value,
            'status' => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'category' => $this->status->category->value,
            ],
            'assignee' => $this->assignee ? new UserResource($this->assignee) : null,
            'reporter' => $this->reporter ? new UserResource($this->reporter) : null,
            'parent_key' => $this->whenLoaded('parent', fn () => $this->parent?->key()),
            'sprint_id' => $this->sprint_id,
            'story_points' => $this->story_points,
            'estimate_minutes' => $this->original_estimate_minutes,
            'due_date' => $this->due_date?->toDateString(),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
