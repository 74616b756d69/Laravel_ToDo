<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('key');
    }

    /**
     * そのユーザーの個人用組織を返す。まだ無ければ作る。
     *
     * Phase 1 では組織を明示的に作らせないので、プロジェクト作成の裏で
     * ここが呼ばれる。組織の作成 UI を入れる段階でこのメソッドは不要になる。
     */
    public static function personalFor(User $user): self
    {
        return $user->ownedOrganizations()->oldest('id')->first()
            ?? $user->ownedOrganizations()->create([
                'name' => "{$user->name} の組織",
                'slug' => self::uniqueSlug($user),
            ]);
    }

    /**
     * 組織スラッグを作る。日本語名だと Str::slug が空になるので、
     * その場合はユーザー ID を種にして必ず何かが残るようにする。
     */
    private static function uniqueSlug(User $user): string
    {
        $base = Str::slug($user->name) ?: "org-{$user->id}";
        $slug = $base;

        for ($suffix = 2; self::where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
