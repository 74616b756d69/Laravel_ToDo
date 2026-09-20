<?php

namespace App\Models;

use App\Enums\ActivityField;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * 課題の変更履歴。
 *
 * **不変**。書いたら二度と変えない・消さない。
 * 履歴を後から書き換えられるなら、それは履歴ではないため。
 * updated_at を持たないのも、更新という概念が無いことを型で示すため。
 */
class Activity extends Model
{
    /** @use HasFactory<\Database\Factories\ActivityFactory> */
    use HasFactory;

    /** 更新しないので updated_at は持たない */
    public const UPDATED_AT = null;

    protected $fillable = ['issue_id', 'user_id', 'field', 'old_value', 'new_value'];

    protected function casts(): array
    {
        return [
            'field' => ActivityField::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // 不変であることをモデル側でも塞ぐ。
        // ルートを生やさないだけでは、うっかりコードから触れてしまう
        static::updating(function () {
            throw new RuntimeException('履歴は変更できません。Activity は追記専用です。');
        });

        static::deleting(function () {
            throw new RuntimeException('履歴は削除できません。Activity は追記専用です。');
        });
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

    /**
     * 履歴 1 行の文。
     */
    public function describe(): string
    {
        return $this->field->describe($this->old_value, $this->new_value);
    }

    /**
     * 表示用の操作者名。コマンドやシーダーからの変更は誰のものでもない。
     */
    public function actorName(): string
    {
        return $this->user?->name ?? 'システム';
    }
}
