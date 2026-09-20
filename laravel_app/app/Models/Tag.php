<?php

namespace App\Models;

use App\Enums\TagColor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory;

    protected $fillable = ['name', 'color'];

    protected function casts(): array
    {
        return ['color' => TagColor::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 中間テーブルは tasks 時代の tag_task をそのまま使う。
     *
     * @return BelongsToMany<Issue, $this>
     */
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'tag_task', 'tag_id', 'task_id');
    }
}
