<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Enums\WebhookFormat;
use App\Exceptions\UnsafeWebhookUrlException;
use App\Models\WebhookDelivery;
use App\Support\Webhooks\WebhookPayload;
use App\Support\Webhooks\WebhookUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Webhook を 1 回送る。失敗したら間隔をあけて送り直す。
 *
 * 送り直すかどうかの決まり:
 *  - 2xx                     … 成功
 *  - 4xx（408 / 429 を除く）  … 相手が「受け取らない」と言っている。何度送っても同じなので諦める
 *  - 5xx・408・429・接続失敗  … 相手の一時的な不調かもしれない。config('webhooks.backoff') の間隔で送り直す
 *  - 送り先が安全でない       … 送らずに諦める（登録後に名前の向き先が内部へ変わった、など）
 *
 * 相手が受け取ったものを検証できるよう、本文に HMAC-SHA256 の署名を付ける。
 * 署名の対象は「タイムスタンプ.本文」。古い通知を録っておいて送り直す（リプレイ）を、
 * 受け手がタイムスタンプで弾けるようにするため。
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 1 回の実行の上限（秒）。送信のタイムアウトより長く取る */
    public int $timeout = 30;

    public function __construct(
        public readonly WebhookDelivery $delivery,
    ) {}

    /** 最初の 1 回 + 再試行の回数 */
    public function tries(): int
    {
        return count(config('webhooks.backoff')) + 1;
    }

    public function handle(WebhookUrlGuard $guard): void
    {
        $delivery = $this->delivery->loadMissing('webhook');
        $webhook = $delivery->webhook;

        $delivery->forceFill(['attempts' => $delivery->attempts + 1])->save();

        try {
            $ips = $guard->resolve($webhook->url);
        } catch (UnsafeWebhookUrlException $e) {
            $this->finish(DeliveryStatus::Failed, error: $e->getMessage());

            return;
        }

        $body = $webhook->format === WebhookFormat::Slack
            ? json_encode(['text' => WebhookPayload::slackText($delivery->payload)], JSON_UNESCAPED_UNICODE)
            : json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;

        try {
            $response = Http::timeout(config('webhooks.timeout'))
                ->connectTimeout(3)
                ->withOptions([
                    // 転送先は確かめていないので、たどらない（転送で内部へ向けられるのを防ぐ）
                    'allow_redirects' => false,
                    // 確かめた IP にだけ接続する
                    'curl' => [CURLOPT_RESOLVE => $this->pinnedResolve($webhook->url, $ips)],
                ])
                ->withHeaders([
                    'User-Agent' => 'Tracklet-Webhook/1.0',
                    'X-Tracklet-Event' => $delivery->event->value,
                    'X-Tracklet-Delivery' => (string) $delivery->id,
                    'X-Tracklet-Timestamp' => $timestamp,
                    'X-Tracklet-Signature' => 'sha256='.hash_hmac('sha256', "{$timestamp}.{$body}", $webhook->secret),
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (ConnectionException $e) {
            $this->retryOrFail(error: Str::limit($e->getMessage(), 480));

            return;
        }

        $this->settle($response);
    }

    private function settle(Response $response): void
    {
        $status = $response->status();
        $body = Str::limit($response->body(), 1000);

        if ($response->successful()) {
            $this->finish(DeliveryStatus::Succeeded, $status, $body);

            return;
        }

        if ($response->clientError() && ! in_array($status, [408, 429], true)) {
            $this->finish(DeliveryStatus::Failed, $status, $body, "相手のサーバーが {$status} を返しました。");

            return;
        }

        $this->retryOrFail($status, $body, "相手のサーバーが {$status} を返しました。");
    }

    /**
     * まだ回数が残っていれば、記録を「送信待ち」のまま間隔をあけて積み直す。
     */
    private function retryOrFail(?int $status = null, ?string $body = null, ?string $error = null): void
    {
        $attempt = $this->attempts();

        if ($attempt >= $this->tries()) {
            $this->finish(DeliveryStatus::Failed, $status, $body, $error);

            return;
        }

        $this->delivery->forceFill([
            'response_status' => $status,
            'response_body' => $body,
            'error' => $error,
        ])->save();

        $this->release(config('webhooks.backoff')[$attempt - 1]);
    }

    private function finish(DeliveryStatus $result, ?int $status = null, ?string $body = null, ?string $error = null): void
    {
        $this->delivery->forceFill([
            'status' => $result,
            'response_status' => $status,
            'response_body' => $body,
            'error' => $error,
            'delivered_at' => $result === DeliveryStatus::Succeeded ? now() : null,
        ])->save();
    }

    /**
     * curl の名前解決を、確かめた IP に固定する（"host:port:ip" の形）。
     *
     * @param  list<string>  $ips
     * @return list<string>
     */
    private function pinnedResolve(string $url, array $ips): array
    {
        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }

        $port = parse_url($url, PHP_URL_PORT) ?? (parse_url($url, PHP_URL_SCHEME) === 'http' ? 80 : 443);
        $ip = str_contains($ips[0], ':') ? "[{$ips[0]}]" : $ips[0];

        return ["{$host}:{$port}:{$ip}"];
    }
}
