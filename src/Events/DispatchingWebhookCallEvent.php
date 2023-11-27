<?php

namespace Spatie\WebhookServer\Events;

class DispatchingWebhookCallEvent
{
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
