<?php

namespace App\Http\Requests\Project;

use App\Enums\WebhookEvent;
use App\Enums\WebhookFormat;
use App\Exceptions\UnsafeWebhookUrlException;
use App\Support\Webhooks\WebhookUrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'url' => ['required', 'string', 'max:2048', 'url'],
            'format' => ['required', Rule::enum(WebhookFormat::class)],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_map(fn (WebhookEvent $event) => $event->value, WebhookEvent::subscribable()))],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => '名前',
            'url' => '送り先 URL',
            'format' => '書式',
            'events' => '送る出来事',
        ];
    }

    /**
     * 送り先が安全か（https・外部のアドレス・名前が引ける）を、保存の前に確かめる。
     * 送る直前にも同じ検査をする（DeliverWebhook）。
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if ($validator->errors()->has('url')) {
                    return;
                }

                try {
                    app(WebhookUrlGuard::class)->resolve($this->input('url'));
                } catch (UnsafeWebhookUrlException $e) {
                    $validator->errors()->add('url', $e->getMessage());
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function webhookAttributes(): array
    {
        return [
            ...$this->safe()->only(['name', 'url', 'format']),
            'events' => array_values(array_unique($this->validated('events'))),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
