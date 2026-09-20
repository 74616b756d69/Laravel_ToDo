<?php

namespace App\Models;

use App\Enums\SprintState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * スプリント。
 *
 * 状態の変更は必ず SprintService を通すこと。
 * 直接 update すると「同時に active は 1 つだけ」が守られない
 * （DB の unique も active_marker を手で入れないと効かない）。
 */
class Sprint extends Model
{
    /** @use HasFactory<\Database\Factories\SprintFactory> */
    use HasFactory;

    protected $fillable = ['name', 'goal', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'state' => SprintState::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Issue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class)->orderBy('position')->orderBy('id');
    }

    public function isFuture(): bool
    {
        return $this->state === SprintState::Future;
    }

    public function isActive(): bool
    {
        return $this->state === SprintState::Active;
    }

    public function isClosed(): bool
    {
        return $this->state === SprintState::Closed;
    }

    /**
     * 期間の表示。どちらか片方だけでも出す。
     */
    public function period(): ?string
    {
        if ($this->start_date === null && $this->end_date === null) {
            return null;
        }

        return trim(sprintf(
            '%s 〜 %s',
            $this->start_date?->isoFormat('M/D') ?? '',
            $this->end_date?->isoFormat('M/D') ?? '',
        ));
    }

    /**
     * 残り日数。終了日が無ければ null。
     */
    public function remainingDays(): ?int
    {
        return $this->end_date === null ? null : (int) today()->diffInDays($this->end_date, false);
    }

    /** @param  Builder<Sprint>  $query */
    public function scopeState(Builder $query, SprintState $state): void
    {
        $query->where('state', $state);
    }

    /**
     * バックログ画面に並べる順。進行中 → 未開始 → 完了。
     *
     * @param  Builder<Sprint>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query
            ->orderByRaw("case state when 'active' then 0 when 'future' then 1 else 2 end")
            ->orderByRaw('start_date is null')
            ->orderBy('start_date')
            ->orderBy('id');
    }
}
