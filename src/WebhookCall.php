<?php

namespace Spatie\WebhookServer;

use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Str;
use Spatie\WebhookServer\BackoffStrategy\BackoffStrategy;
use Spatie\WebhookServer\Events\DispatchingWebhookCallEvent;
use Spatie\WebhookServer\Exceptions\CouldNotCallWebhook;
use Spatie\WebhookServer\Exceptions\InvalidBackoffStrategy;
use Spatie\WebhookServer\Exceptions\InvalidSigner;
use Spatie\WebhookServer\Exceptions\InvalidWebhookJob;
use Spatie\WebhookServer\Signer\Signer;

/** @phpstan-consistent-constructor */
class WebhookCall
{
    protected CallWebhookJob $callWebhookJob;

    protected string $uuid = '';

    protected string $secret;

    protected Signer $signer;

    /** @var array<string, string|list<string>> */
    protected array $headers = [];

    protected ?string $webhookIdHeaderName = 'Webhook-Id';

    private bool $signWebhook = true;

    public static function create(): self
    {
        $config = config('webhook-server');

        return (new static())
            ->useJob($config['webhook_job'])
            ->uuid(Str::uuid())
            ->onQueue($config['queue'])
            ->onConnection(config('webhook-server.connection'))
            ->useHttpVerb($config['http_verb'])
            ->maximumTries($config['tries'])
            ->useBackoffStrategy($config['backoff_strategy'])
            ->timeoutInSeconds($config['timeout_in_seconds'])
            ->signUsing($config['signer'])
            ->withHeaders($config['headers'])
            ->withWebhookIdHeader($config['webhook_id_header_name'])
            ->withTags($config['tags'])
            ->verifySsl($config['verify_ssl'])
            ->throwExceptionOnFailure($config['throw_exception_on_failure'])
            ->useProxy($config['proxy']);
    }

    public function __construct()
    {
    }

    public function url(string $url): self
    {
        $this->callWebhookJob->webhookUrl = $url;

        return $this;
    }

    /** @param array<array-key, mixed> $payload */
    public function payload(array $payload): self
    {
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

    public function getUrl(): ?string
    {
        return $this->callWebhookJob->webhookUrl;
    }

    public function onQueue(?string $queue): self
    {
        $this->callWebhookJob->queue = $queue;

        return $this;
    }

    public function onConnection(?string $connection): self
    {
        $this->callWebhookJob->connection = $connection;

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

    public function mutualTls(string $certPath, string $sslKeyPath, ?string $certPassphrase = null, ?string $sslKeyPassphrase = null): self
    {
        $this->callWebhookJob->cert = $certPath;
        $this->callWebhookJob->certPassphrase = $certPassphrase;
        $this->callWebhookJob->sslKey = $sslKeyPath;
        $this->callWebhookJob->sslKeyPassphrase = $sslKeyPassphrase;

        return $this;
    }

    /** @param class-string<BackoffStrategy> $backoffStrategyClass */
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

    /** @param class-string<Signer> $signerClass */
    public function signUsing(string $signerClass): self
    {
        if (! is_subclass_of($signerClass, Signer::class)) {
            throw InvalidSigner::doesNotImplementSigner($signerClass);
        }

        /** @var Signer $signer */
        $signer = app($signerClass);

        $this->signer = $signer;

        return $this;
    }

    public function doNotSign(): self
    {
        $this->signWebhook = false;

        return $this;
    }

    /** @param array<string, string|list<string>> $headers */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function withWebhookIdHeader(?string $headerName): self
    {
        $this->webhookIdHeaderName = $headerName;

        return $this;
    }

    public function verifySsl(bool|string $verifySsl = true): self
    {
        $this->callWebhookJob->verifySsl = $verifySsl;

        return $this;
    }

    public function doNotVerifySsl(): self
    {
        $this->verifySsl(false);

        return $this;
    }

    public function throwExceptionOnFailure(bool $throwExceptionOnFailure = true): self
    {
        $this->callWebhookJob->throwExceptionOnFailure = $throwExceptionOnFailure;

        return $this;
    }

    /** @param array<string, string|list<string>>|string|null $proxy */
    public function useProxy(array|string|null $proxy = null): self
    {
        $this->callWebhookJob->proxy = $proxy;

        return $this;
    }

    /** @param array<array-key, mixed> $meta */
    public function meta(array $meta): self
    {
        $this->callWebhookJob->meta = $meta;

        return $this;
    }

    /** @param array<array-key, string> $tags */
    public function withTags(array $tags): self
    {
        $this->callWebhookJob->tags = $tags;

        return $this;
    }

    /** @param class-string $webhookJobClass */
    public function useJob(string $webhookJobClass): self
    {
        $job = app($webhookJobClass);

        if (! $job instanceof CallWebhookJob) {
            throw InvalidWebhookJob::doesNotExtendCallWebhookJob($webhookJobClass);
        }

        $this->callWebhookJob = $job;

        return $this;
    }

    public function useTimestamp(bool $use = true): self
    {
        $this->callWebhookJob->useTimestamp = $use;

        return $this;
    }

    public function dispatch(): PendingDispatch
    {
        $this->prepareForDispatch();

        event(new DispatchingWebhookCallEvent(
            $this->callWebhookJob->httpVerb,
            $this->callWebhookJob->getWebhookUrl(),
            $this->callWebhookJob->payload,
            $this->callWebhookJob->headers,
            $this->callWebhookJob->meta,
            $this->callWebhookJob->tags,
            $this->callWebhookJob->uuid,
        ));

        return dispatch($this->callWebhookJob);
    }

    public function dispatchIf(mixed $condition): ?PendingDispatch
    {
        if ($condition) {
            return $this->dispatch();
        }

        return null;
    }

    public function dispatchUnless(mixed $condition): ?PendingDispatch
    {
        return $this->dispatchIf(! $condition);
    }

    public function dispatchSync(): void
    {
        $this->prepareForDispatch();

        dispatch_sync($this->callWebhookJob);
    }

    public function dispatchSyncIf(mixed $condition): void
    {
        if ($condition) {
            $this->dispatchSync();
        }
    }

    public function dispatchSyncUnless(mixed $condition): void
    {
        $this->dispatchSyncIf(! $condition);
    }

    public function sendRawBody(string $body): self
    {
        $this->callWebhookJob->payload = $body;
        $this->callWebhookJob->outputType = 'RAW';

        return $this;
    }

    protected function prepareForDispatch(): void
    {
        $this->callWebhookJob->getWebhookUrl();

        if ($this->signWebhook && empty($this->secret)) {
            throw CouldNotCallWebhook::secretNotSet();
        }

        $this->callWebhookJob->preparedBody = null;

        if (strtoupper($this->callWebhookJob->httpVerb) !== 'GET') {
            $this->callWebhookJob->getBody();
        }

        $this->callWebhookJob->headers = $this->getAllHeaders();
    }

    /** @return array<string, string|list<string>> */
    protected function getAllHeaders(): array
    {
        $headers = $this->headers;
        $idHeader = $this->webhookIdHeaderName;

        if ($idHeader !== null) {
            if (! array_any(array_keys($headers), fn (string $name) => strcasecmp($name, $idHeader) === 0)) {
                $headers[$idHeader] = $this->callWebhookJob->uuid;
            }
        }

        if (! $this->signWebhook) {
            return $headers;
        }

        $signature = $this->signer->calculateSignature($this->callWebhookJob->getWebhookUrl(), $this->callWebhookJob->getBody(), $this->secret);

        $headers[$this->signer->signatureHeaderName()] = $signature;

        return $headers;
    }
}
