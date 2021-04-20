<?php

namespace Spatie\WebhookServer\Events;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\TransferStats;

abstract class WebhookCallEvent
{
    public string $httpVerb;

    public string $webhookUrl;

    public array $payload;

    public array $headers;

    public array $meta;

    public array $tags;

    public int $attempt;

    public ?Response $response;

    public ?string $errorType;

    public ?string $errorMessage;

    public string $uuid;

    public ?TransferStats $transferStats;

    public function __construct(
        string $httpVerb,
        string $webhookUrl,
        array $payload,
        array $headers,
        array $meta,
        array $tags,
        int $attempt,
        ?Response $response,
        ?string $errorType,
        ?string $errorMessage,
        string $uuid,
        ?TransferStats $transferStats
    ) {
        $this->httpVerb = $httpVerb;
        $this->webhookUrl = $webhookUrl;
        $this->payload = $payload;
        $this->headers = $headers;
        $this->meta = $meta;
        $this->tags = $tags;
        $this->attempt = $attempt;
        $this->response = $response;
        $this->errorType = $errorType;
        $this->errorMessage = $errorMessage;
        $this->uuid = $uuid;
        $this->transferStats = $transferStats;
    }
}
