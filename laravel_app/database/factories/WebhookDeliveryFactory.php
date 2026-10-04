<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event' => WebhookEvent::Ping,
            'payload' => ['event' => 'ping'],
            'status' => DeliveryStatus::Pending,
        ];
    }
}
