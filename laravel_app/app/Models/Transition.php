<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ワークフロー上の 1 本の矢印。
 *
 * from_status_id が null の行は「どのステータスからでも to へ移せる」を表す
 * （Jira でいう global transition）。差し戻しや再オープンをこれで表現する。
 */
class Transition extends Model
{
    /** @use HasFactory<\Database\Factories\TransitionFactory> */
    use HasFactory;

    protected $fillable = ['from_status_id', 'to_status_id'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Status, $this> */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    /** @return BelongsTo<Status, $this> */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    public function isGlobal(): bool
    {
        return $this->from_status_id === null;
    }
}
