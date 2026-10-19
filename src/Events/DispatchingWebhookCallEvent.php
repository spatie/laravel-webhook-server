<?php

namespace Spatie\WebhookServer\Events;

use Spatie\WebhookServer\Concerns\SerializesWebhookData;

class DispatchingWebhookCallEvent
{
    use SerializesWebhookData;

    /**
     * @param string $httpVerb
     * @param string $webhookUrl
     * @param array<array-key, mixed>|string $payload
     * @param array<string, string|list<string>> $headers
     * @param array<array-key, mixed> $meta
     * @param array<array-key, string> $tags
     * @param string $uuid
     */
    public function __construct(
        public string $httpVerb,
        public string $webhookUrl,
        public array|string $payload,
        public array $headers,
        public array $meta,
        public array $tags,
        public string $uuid,
    ) {
    }
}
