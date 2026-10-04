<?php

namespace App\Support\Webhooks;

use App\Exceptions\UnsafeWebhookUrlException;
use Closure;

/**
 * Webhook の送り先を確かめ、接続してよい IP アドレスを返す。
 *
 * 確かめるのは登録時と、送る直前の 2 回。登録時に外部を指していた名前が、
 * あとから内部のアドレスに付け替えられる（DNS リバインディング）ことがあるため。
 * さらに送信ではここで引いた IP に接続先を固定し、検査と接続の間で
 * 名前の解決結果が変わる隙も塞ぐ（DeliverWebhook の CURLOPT_RESOLVE）。
 */
class WebhookUrlGuard
{
    /**
     * PHP の判定（NO_PRIV_RANGE / NO_RES_RANGE）が素通しする、外に出ない IPv4 の範囲。
     * キャリアグレード NAT と、IETF / ベンチマーク用の予約。
     */
    private const EXTRA_BLOCKED_V4 = ['100.64.0.0/10', '192.0.0.0/24', '198.18.0.0/15'];

    /** @var Closure(string): list<string> */
    private readonly Closure $resolver;

    /**
     * @param  (Closure(string): list<string>)|null  $resolver  名前を IP に引く関数。テストで差し替える
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? self::dns(...);
    }

    /**
     * 送ってよい URL なら、接続先の IP アドレスを返す。だめなら理由つきで例外を投げる。
     *
     * @return list<string>
     */
    public function resolve(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeWebhookUrlException('URL の形式が正しくありません。');
        }

        $scheme = strtolower($parts['scheme']);
        $allowed = config('webhooks.allow_http') ? ['https', 'http'] : ['https'];

        if (! in_array($scheme, $allowed, true)) {
            throw new UnsafeWebhookUrlException('送り先は https の URL にしてください。');
        }

        // user:pass@host は、画面で伏せた URL から鍵が漏れる原因になるので受けない
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeWebhookUrlException('URL に認証情報（user:pass@）は含められません。');
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);

        if ($ips === []) {
            throw new UnsafeWebhookUrlException("「{$host}」の名前を解決できませんでした。");
        }

        if (! config('webhooks.allow_private_hosts')) {
            foreach ($ips as $ip) {
                if (! self::isPublic($ip)) {
                    throw new UnsafeWebhookUrlException('内部ネットワークのアドレスには送れません。');
                }
            }
        }

        return array_values($ips);
    }

    /**
     * インターネット上のアドレスか。プライベート・予約済み（ループバック、リンクローカル、
     * メタデータの 169.254.0.0/16 を含む）は除く。
     */
    public static function isPublic(string $ip): bool
    {
        // ::ffff:127.0.0.1 のような IPv4 射影アドレスは、中の IPv4 で判定する
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $matches)) {
            $ip = $matches[1];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return true;
        }

        foreach (self::EXTRA_BLOCKED_V4 as $cidr) {
            [$network, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);

            if ((ip2long($ip) & $mask) === (ip2long($network) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function dns(string $host): array
    {
        $v4 = gethostbynamel($host) ?: [];
        $v6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_unique([...$v4, ...$v6]));
    }
}
