<?php

namespace Spatie\WebhookServer\Events;

use GuzzleHttp\Psr7\Response;

abstract class WebhookCallEvent
{
    /** @var string */
    public string $httpVerb;

    /** @var string */
    public string $webhookUrl;

    /** @var array */
    public array $payload;

    /** @var array */
    public array $headers;

    /** @var array */
    public array $meta;

    /** @var array */
    public array $tags;

    /** @var int */
    public int $attempt;

    /** @var \GuzzleHttp\Psr7\Response|null */
    public ?Response $response;

    /** @var string */
    public ?string $errorType;

    /** @var string */
    public ?string $errorMessage;

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
        ?string $errorMessage
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
    }
}
