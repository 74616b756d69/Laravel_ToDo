<?php

namespace App\Models;

use App\Enums\IssueLinkType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 課題どうしの関連 1 本。
 *
 * 行は 1 本しか持たず、逆向きは「見る側を変えて読む」ことで表す。
 * 両方向に行を作ると、片側だけ消える不整合が起きうるため。
 */
class IssueLink extends Model
{
    /** @use HasFactory<\Database\Factories\IssueLinkFactory> */
    use HasFactory;

    protected $fillable = ['source_issue_id', 'target_issue_id', 'type'];

    protected function casts(): array
    {
        return ['type' => IssueLinkType::class];
    }

    /** @return BelongsTo<Issue, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'source_issue_id');
    }

    /** @return BelongsTo<Issue, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'target_issue_id');
    }

    /**
     * 指定の課題から見た「相手」。
     */
    public function counterpartFor(Issue $issue): Issue
    {
        return $this->source_issue_id === $issue->id ? $this->target : $this->source;
    }

    /**
     * 指定の課題から見た読み方。
     */
    public function labelFor(Issue $issue): string
    {
        return $this->source_issue_id === $issue->id
            ? $this->type->outwardLabel()
            : $this->type->inwardLabel();
    }
}
