<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\WebhookEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Webhook の配信 1 回ぶんの記録。
 *
 * 中身はイベントの時点で写し取っておき、再試行でも再送でも同じものを送る。
 * 古い記録は model:prune で消す（routes/console.php で毎日）。
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookDeliveryFactory> */
    use HasFactory, MassPrunable;

    /** 記録を残す日数 */
    public const RETENTION_DAYS = 30;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'event' => WebhookEvent::class,
            'payload' => 'array',
            'status' => DeliveryStatus::class,
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Webhook, $this> */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * @return Builder<WebhookDelivery>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
