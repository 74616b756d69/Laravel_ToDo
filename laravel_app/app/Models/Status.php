<?php

namespace App\Models;

use App\Enums\StatusCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * プロジェクトごとのワークフローの 1 状態。カンバンの 1 レーンにあたる。
 */
class Status extends Model
{
    /** @use HasFactory<\Database\Factories\StatusFactory> */
    use HasFactory;

    protected $fillable = ['name', 'category', 'position'];

    protected function casts(): array
    {
        return ['category' => StatusCategory::class];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Issue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class, 'status_id');
    }

    /** ここから出ていける遷移。 @return HasMany<Transition, $this> */
    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(Transition::class, 'from_status_id');
    }

    /** ここへ入ってこられる遷移。 @return HasMany<Transition, $this> */
    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(Transition::class, 'to_status_id');
    }

    public function isDone(): bool
    {
        return $this->category->isDone();
    }

    public function badgeClasses(): string
    {
        return $this->category->badgeClasses();
    }

    public function dotClasses(): string
    {
        return $this->category->dotClasses();
    }

    /**
     * 名前で引く。ワークフローの定義・テスト・シーダーで使う。
     *
     * @param  Builder<Status>  $query
     */
    public function scopeNamed(Builder $query, string $name): void
    {
        $query->where('name', $name);
    }

    /** @param  Builder<Status>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }
}
