<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;

/**
 * 配信の記録を作って、送信をキューに積む。
 *
 * 送信そのもの（相手のサーバーを待つ）はリクエストの中でやらない。
 * 相手が遅い・落ちている、で利用者の操作まで遅くならないようにするため。
 */
class WebhookDispatcher
{
    /**
     * プロジェクトの有効な Webhook のうち、この出来事を購読しているものすべてへ送る。
     *
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(int $projectId, WebhookEvent $event, array $payload): void
    {
        Webhook::query()
            ->where('project_id', $projectId)
            ->active()
            ->get()
            ->filter(fn (Webhook $webhook) => $webhook->subscribesTo($event))
            ->each(fn (Webhook $webhook) => $this->send($webhook, $event, $payload));
    }

    /**
     * 1 つの Webhook へ送る。テスト送信と再送はここを直接呼ぶ。
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(Webhook $webhook, WebhookEvent $event, array $payload): WebhookDelivery
    {
        $delivery = $webhook->deliveries()->make()->forceFill([
            'event' => $event,
            'payload' => $payload,
            'status' => DeliveryStatus::Pending,
        ]);
        $delivery->save();

        DeliverWebhook::dispatch($delivery);

        return $delivery;
    }
}
