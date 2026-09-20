<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * プロジェクトへの所属と役割。
 *
 * 中間テーブルだが役割という属性を持つので、Pivot を継承したうえで
 * 独立したモデルとしても扱えるようにしている（$project->members() で使う）。
 */
class ProjectMember extends Pivot
{
    /** @use HasFactory<\Database\Factories\ProjectMemberFactory> */
    use HasFactory;

    protected $table = 'project_members';

    public $incrementing = true;

    protected $fillable = ['project_id', 'user_id', 'role'];

    protected function casts(): array
    {
        return ['role' => ProjectRole::class];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
