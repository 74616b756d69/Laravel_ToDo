<?php

namespace App\Enums;

/**
 * 送る本文の書式。
 */
enum WebhookFormat: string
{
    /** 署名付きの JSON。自前のサーバーや自動化ツールで受ける */
    case Generic = 'generic';

    /** Slack の Incoming Webhook が読める { "text": ... } */
    case Slack = 'slack';

    public function label(): string
    {
        return match ($this) {
            self::Generic => 'JSON（署名付き）',
            self::Slack => 'Slack',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $format) => [$format->value => $format->label()])->all();
    }
}
