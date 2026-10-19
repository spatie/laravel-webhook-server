<?php

namespace Spatie\WebhookServer\Events;

use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Response;

abstract class WebhookCallEvent
{
    /**
     * @param string $httpVerb
     * @param string $webhookUrl
     * @param array<array-key, mixed>|string $payload
     * @param array<string, string|list<string>> $headers
     * @param array<array-key, mixed> $meta
     * @param array<array-key, string> $tags
     * @param int $attempt
     * @param Response|null $response
     * @param string|null $errorType
     * @param string|null $errorMessage
     * @param string $uuid
     * @param TransferStats|null $transferStats
     */
    public function __construct(
        public string $httpVerb,
        public string $webhookUrl,
        public array|string $payload,
        public array $headers,
        public array $meta,
        public array $tags,
        public int $attempt,
        public ?Response $response,
        public ?string $errorType,
        public ?string $errorMessage,
        public string $uuid,
        public ?TransferStats $transferStats
    ) {
    }
}
