<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** 自分が起票した課題。 @return HasMany<Issue, $this> */
    public function reportedIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'reporter_id');
    }

    /** 自分に割り当てられた課題。 @return HasMany<Issue, $this> */
    public function assignedIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'assignee_id');
    }

    /** @return HasMany<Tag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class)->orderBy('name');
    }

    /** @return HasMany<Organization, $this> */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /** @return HasMany<ProjectMember, $this> */
    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * 所属しているプロジェクト。役割は $project->membership->role で取れる。
     *
     * @return BelongsToMany<Project, $this, ProjectMember>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->using(ProjectMember::class)
            ->withPivot('role')
            ->withTimestamps()
            ->as('membership')
            ->orderBy('key');
    }
}
