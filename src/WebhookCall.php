<?php

namespace Spatie\WebhookServer;

use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Str;
use Spatie\WebhookServer\BackoffStrategy\BackoffStrategy;
use Spatie\WebhookServer\Exceptions\CouldNotCallWebhook;
use Spatie\WebhookServer\Exceptions\InvalidBackoffStrategy;
use Spatie\WebhookServer\Exceptions\InvalidSigner;
use Spatie\WebhookServer\Signer\Signer;

class WebhookCall
{
    protected CallWebhookJob $callWebhookJob;

    protected string $uuid = '';

    protected string $secret;

    protected Signer $signer;

    protected array $headers = [];

    private array $payload = [];

    private $signWebhook = true;

    public static function create(): self
    {
        $config = config('webhook-server');

        return (new static())
            ->uuid(Str::uuid())
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

    public function url(string $url): self
    {
        $this->callWebhookJob->webhookUrl = $url;

        return $this;
    }

    public function payload(array $payload): self
    {
        $this->payload = $payload;

        $this->callWebhookJob->payload = $payload;

        return $this;
    }

    public function uuid(string $uuid): self
    {
        $this->uuid = $uuid;

        $this->callWebhookJob->uuid = $uuid;

        return $this;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function onQueue(string $queue): self
    {
        $this->callWebhookJob->queue = $queue;

        return $this;
    }

    public function useSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function useHttpVerb(string $verb): self
    {
        $this->callWebhookJob->httpVerb = $verb;

        return $this;
    }

    public function maximumTries(int $tries): self
    {
        $this->callWebhookJob->tries = $tries;

        return $this;
    }

    public function useBackoffStrategy(string $backoffStrategyClass): self
    {
        if (! is_subclass_of($backoffStrategyClass, BackoffStrategy::class)) {
            throw InvalidBackoffStrategy::doesNotExtendBackoffStrategy($backoffStrategyClass);
        }

        $this->callWebhookJob->backoffStrategyClass = $backoffStrategyClass;

        return $this;
    }

    public function timeoutInSeconds(int $timeoutInSeconds): self
    {
        $this->callWebhookJob->requestTimeout = $timeoutInSeconds;

        return $this;
    }

    public function signUsing(string $signerClass): self
    {
        if (! is_subclass_of($signerClass, Signer::class)) {
            throw InvalidSigner::doesNotImplementSigner($signerClass);
        }

        $this->signer = app($signerClass);

        return $this;
    }

    public function doNotSign(): self
    {
        $this->signWebhook = false;

        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function verifySsl(bool $verifySsl = true): self
    {
        $this->callWebhookJob->verifySsl = $verifySsl;

        return $this;
    }

    public function doNotVerifySsl(): self
    {
        $this->verifySsl(false);

        return $this;
    }

    public function meta(array $meta): self
    {
        $this->callWebhookJob->meta = $meta;

        return $this;
    }

    public function withTags(array $tags): self
    {
        $this->callWebhookJob->tags = $tags;

        return $this;
    }

    public function dispatch(): PendingDispatch
    {
        $this->prepareForDispatch();

        return dispatch($this->callWebhookJob);
    }

    /**
     * @deprecated Will be removed in a future version in favor of dispatchSync
     */
    public function dispatchNow(): void
    {
        $this->dispatchSync();
    }

    public function dispatchSync(): void
    {
        $this->prepareForDispatch();

        function_exists('dispatch_sync')
            ? dispatch_sync($this->callWebhookJob)
            : dispatch_now($this->callWebhookJob);
    }

    protected function prepareForDispatch(): void
    {
        if (! $this->callWebhookJob->webhookUrl) {
            throw CouldNotCallWebhook::urlNotSet();
        }

        if ($this->signWebhook && empty($this->secret)) {
            throw CouldNotCallWebhook::secretNotSet();
        }

        $this->callWebhookJob->headers = $this->getAllHeaders();
    }

    protected function getAllHeaders(): array
    {
        $headers = $this->headers;

        if (! $this->signWebhook) {
            return $headers;
        }

        $signature = $this->signer->calculateSignature($this->payload, $this->secret);

        $headers[$this->signer->signatureHeaderName()] = $signature;

        return $headers;
    }
}
