<?php

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Enums\WebhookFormat;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Webhook>
 */
class WebhookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => 'CI 連携',
            'url' => 'https://hooks.example.com/tracklet',
            'format' => WebhookFormat::Generic,
            'events' => array_map(fn (WebhookEvent $event) => $event->value, WebhookEvent::subscribable()),
            'is_active' => true,
        ];
    }

    public function slack(): static
    {
        return $this->state(fn () => [
            'format' => WebhookFormat::Slack,
            'url' => 'https://hooks.slack.com/services/T000/B000/XXXX',
        ]);
    }

    /**
     * @param  list<WebhookEvent>  $events
     */
    public function listening(array $events): static
    {
        return $this->state(fn () => ['events' => array_map(fn (WebhookEvent $event) => $event->value, $events)]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
