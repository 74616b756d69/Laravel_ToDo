<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use App\Enums\WebhookFormat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * プロジェクトの出来事を外部の URL へ知らせる設定。
 *
 * 署名の鍵（secret）はサーバーが発行し、暗号化して保存する。
 * DB が漏れても、鍵が無ければ偽の通知を作れないようにするため。
 */
class Webhook extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookFactory> */
    use HasFactory;

    protected $fillable = ['name', 'url', 'format', 'events', 'is_active'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'format' => WebhookFormat::class,
            'events' => 'array',
            'is_active' => 'boolean',
            'secret' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Webhook $webhook) {
            $webhook->secret ??= self::generateSecret();
        });
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest()->latest('id');
    }

    public function subscribesTo(WebhookEvent $event): bool
    {
        return in_array($event->value, $this->events ?? [], true);
    }

    /**
     * @param  Builder<Webhook>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * 画面に出す URL。パスやクエリに鍵が入っていることが多い（Slack など）ので、
     * ホストより後ろは伏せる。
     */
    public function maskedUrl(): string
    {
        $parts = parse_url($this->url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return '••••';
        }

        return "{$parts['scheme']}://{$parts['host']}/••••";
    }
}
