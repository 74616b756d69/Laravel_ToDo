<?php

namespace App\Models;

use App\Support\Duration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 作業時間の記録 1 件。誰が・いつ（作業した日）・何分。
 */
class Worklog extends Model
{
    /** @use HasFactory<\Database\Factories\WorklogFactory> */
    use HasFactory;

    /** 記録者は利用者に決めさせない（Comment と同じ理由） */
    protected $fillable = ['minutes', 'worked_on', 'comment'];

    protected function casts(): array
    {
        return ['worked_on' => 'date'];
    }

    /** @return BelongsTo<Issue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function duration(): string
    {
        return Duration::format($this->minutes);
    }

    public function authorName(): string
    {
        return $this->user?->name ?? '削除されたユーザー';
    }
}
