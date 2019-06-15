<?php

namespace Spatie\WebhookServer;

use Spatie\WebhookServer\Signer\Signer;
use Spatie\WebhookServer\Exceptions\InvalidSigner;
use Spatie\WebhookServer\Exceptions\CouldNotCallWebhook;
use Spatie\WebhookServer\BackoffStrategy\BackoffStrategy;
use Spatie\WebhookServer\Exceptions\InvalidBackoffStrategy;

class WebhookCall
{
    /** @var \Spatie\WebhookServer\CallWebhookJob */
    protected $callWebhookJob;

    /** @var string */
    protected $secret;

    /** @var \Spatie\WebhookServer\Signer\Signer */
    protected $signer;

    /** @var array */
    protected $headers = [];

    /** @var array */
    private $payload = [];

    public static function create(): self
    {
        $config = config('webhook-server');

        return (new static())
            ->onQueue($config['queue'])
            ->useHttpVerb($config['http_verb'])
            ->maximumTries($config['tries'])
            ->useBackoffStrategy($config['backoff_strategy'])
            ->timeoutInSeconds($config['timeout_in_seconds'])
            ->signUsing($config['signer'])
            ->withHeaders($config['headers'])
            ->withTags($config['tags'])
            ->verifySsl($config['verify_ssl']);
    }

    public function __construct()
    {
        $this->callWebhookJob = app(CallWebhookJob::class);
    }

    public function url(string $url)
    {
        $this->callWebhookJob->webhookUrl = $url;

        return $this;
    }

    public function payload(array $payload)
    {
        $this->payload = $payload;

        $this->callWebhookJob->payload = $payload;

        return $this;
    }

    public function onQueue(string $queue)
    {
        $this->callWebhookJob->queue = $queue;

        return $this;
    }

    public function useSecret(string $secret)
    {
        $this->secret = $secret;

        return $this;
    }

    public function useHttpVerb(string $verb)
    {
        $this->callWebhookJob->httpVerb = $verb;

        return $this;
    }

    public function maximumTries(int $tries)
    {
        $this->callWebhookJob->tries = $tries;

        return $this;
    }

    public function useBackoffStrategy(string $backoffStrategyClass)
    {
        if (! is_subclass_of($backoffStrategyClass, BackoffStrategy::class)) {
            throw InvalidBackoffStrategy::doesNotExtendBackoffStrategy($backoffStrategyClass);
        }

        $this->callWebhookJob->backoffStrategyClass = $backoffStrategyClass;

        return $this;
    }

    public function timeoutInSeconds(int $timeoutInSeconds)
    {
        $this->callWebhookJob->requestTimeout = $timeoutInSeconds;

        return $this;
    }

    public function signUsing(string $signerClass)
    {
        if (! is_subclass_of($signerClass, Signer::class)) {
            throw InvalidSigner::doesImplementSigner($signerClass);
        }

        $this->signer = app($signerClass);

        return $this;
    }

    public function withHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    public function verifySsl(bool $verifySsl = true)
    {
        $this->callWebhookJob->verifySsl = $verifySsl;

        return $this;
    }

    public function doNotVerifySsl()
    {
        $this->verifySsl(false);

        return $this;
    }

    public function meta(array $meta)
    {
        $this->callWebhookJob->meta = $meta;

        return $this;
    }

    public function withTags(array $tags)
    {
        $this->callWebhookJob->tags = $tags;

        return $this;
    }

    public function dispatch(): void
    {
        if (! $this->callWebhookJob->webhookUrl) {
            throw CouldNotCallWebhook::urlNotSet();
        }

        if (empty($this->secret)) {
            throw CouldNotCallWebhook::secretNotSet();
        }

        $this->callWebhookJob->headers = $this->getAllHeaders();

        dispatch($this->callWebhookJob);
    }

    protected function getAllHeaders(): array
    {
        $headers = $this->headers;

        $signature = $this->signer->calculateSignature($this->payload, $this->secret);

        $headers[$this->signer->signatureHeaderName()] = $signature;

        return $headers;
    }
}
