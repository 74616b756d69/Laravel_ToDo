<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'status',
        'priority',
        'due_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === TaskStatus::Done;
    }

    /**
     * 期限切れ（未完了かつ期限が過ぎている）。
     */
    public function isOverdue(): bool
    {
        return ! $this->isCompleted()
            && $this->due_date !== null
            && $this->due_date->isBefore(today());
    }

    /**
     * 期限が今日・明日など「もうすぐ」かどうか。
     */
    public function isDueSoon(): bool
    {
        return ! $this->isCompleted()
            && ! $this->isOverdue()
            && $this->due_date !== null
            && $this->due_date->isBefore(today()->addDays(3));
    }

    /**
     * 完了/未完了をトグルする。完了時刻も併せて面倒を見る。
     */
    public function toggleCompletion(): void
    {
        $this->forceFill($this->isCompleted()
            ? ['status' => TaskStatus::Todo, 'completed_at' => null]
            : ['status' => TaskStatus::Done, 'completed_at' => now()]
        )->save();
    }

    /**
     * タイトル・本文の部分一致検索。
     *
     * @param  Builder<Task>  $query
     */
    public function scopeSearch(Builder $query, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);

        if ($keyword === '') {
            return;
        }

        // LIKE のワイルドカードを打ち消してから部分一致に使う
        $escaped = addcslashes($keyword, '%_\\');

        $query->where(function (Builder $query) use ($escaped) {
            $query->where('title', 'like', "%{$escaped}%")
                ->orWhere('content', 'like', "%{$escaped}%");
        });
    }

    /** @param  Builder<Task>  $query */
    public function scopeStatus(Builder $query, ?TaskStatus $status): void
    {
        $query->when($status, fn (Builder $query) => $query->where('status', $status));
    }

    /** @param  Builder<Task>  $query */
    public function scopePriority(Builder $query, ?TaskPriority $priority): void
    {
        $query->when($priority, fn (Builder $query) => $query->where('priority', $priority));
    }

    /** @param  Builder<Task>  $query */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNot('status', TaskStatus::Done)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    }

    /**
     * 指定キーで並び替える。優先度は enum の重み順に並べたいので CASE 式を使う。
     *
     * @param  Builder<Task>  $query
     */
    public function scopeSorted(Builder $query, string $sort): void
    {
        match ($sort) {
            'due_date' => $query
                // 期限なしは最後に回す
                ->orderByRaw('due_date is null')
                ->orderBy('due_date')
                ->orderByDesc('id'),
            'priority' => $query
                ->orderByRaw(self::priorityCaseExpression().' desc')
                ->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    private static function priorityCaseExpression(): string
    {
        $cases = collect(TaskPriority::cases())
            ->map(fn (TaskPriority $priority) => sprintf(
                "when '%s' then %d",
                $priority->value,
                $priority->weight(),
            ))
            ->implode(' ');

        return "case priority {$cases} else 0 end";
    }
}
