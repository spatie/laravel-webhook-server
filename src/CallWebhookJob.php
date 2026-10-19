<?php

namespace Spatie\WebhookServer;

use Exception;
use GuzzleHttp\TransferStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Spatie\WebhookServer\BackoffStrategy\BackoffStrategy;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
use Spatie\WebhookServer\Exceptions\CouldNotCallWebhook;
use Spatie\WebhookServer\Signer\Signer;
use Throwable;
use UnexpectedValueException;

class CallWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public ?string $webhookUrl = null;

    public string $httpVerb;

    /** @var string|array<string, string|list<string>>|null */
    public string|array|null $proxy = null;

    public int $tries;

    public int $requestTimeout;

    public ?string $cert = null;

    public ?string $certPassphrase = null;

    public ?string $sslKey = null;

    public ?string $sslKeyPassphrase = null;

    /** @var class-string<BackoffStrategy> */
    public string $backoffStrategyClass;

    /** @var class-string<Signer>|null */
    public ?string $signerClass = null;

    /** @var array<string, string|list<string>> */
    public array $headers = [];

    public string|bool $verifySsl;

    public bool $useTimestamp = false;

    public bool $throwExceptionOnFailure;

    /** @var string|null */
    public $queue = null;

    /** @var array<array-key, mixed>|string */
    public array|string $payload = [];

    public ?string $preparedBody = null;

    /** @var array<array-key, mixed> */
    public array $meta = [];

    /** @var array<array-key, string> */
    public array $tags = [];

    public string $uuid = '';

    public string $outputType = 'JSON';

    protected ?Response $response = null;

    protected ?string $errorType = null;

    protected ?string $errorMessage = null;

    protected ?TransferStats $transferStats = null;

    public function handle(): void
    {
        $lastAttempt = $this->attempts() >= $this->tries;

        try {
            if ($this->useTimestamp) {
                $this->addTimestampToHeaders();
            }

            $this->response = $this->createRequest()->send(
                strtoupper($this->httpVerb),
                $this->getWebhookUrl(),
                $this->isGetRequest()
                    ? ['query' => $this->payload]
                    : ['body' => $this->getBody()],
            );

            if (! $this->response->successful()) {
                throw new Exception('Webhook call failed');
            }

            $this->dispatchEvent(WebhookCallSucceededEvent::class);
        } catch (Exception $exception) {
            if ($exception instanceof RequestException) {
                $this->response = $exception->response;
            }

            $this->errorType = $exception::class;
            $this->errorMessage = $exception->getMessage();

            if (! $lastAttempt) {
                /** @var BackoffStrategy $backoffStrategy */
                $backoffStrategy = app($this->backoffStrategyClass);

                $waitInSeconds = $backoffStrategy->waitInSecondsAfterAttempt($this->attempts());

                $this->release($waitInSeconds);
            }

            $this->dispatchEvent(WebhookCallFailedEvent::class);

            if ($lastAttempt || $this->shouldBeRemovedFromQueue()) {
                $this->dispatchEvent(FinalWebhookCallFailedEvent::class);

                $this->throwExceptionOnFailure ? $this->fail($exception) : $this->delete();
            }
        }
    }

    /** @return array<array-key, string> */
    public function tags(): array
    {
        return $this->tags;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function getBody(): string
    {
        return $this->preparedBody ??= $this->generateBody();
    }

    public function getWebhookUrl(): string
    {
        if (! $this->webhookUrl) {
            throw CouldNotCallWebhook::urlNotSet();
        }

        return $this->webhookUrl;
    }

    protected function createRequest(): PendingRequest
    {
        return Http::withHeaders($this->headers)
            ->timeout($this->requestTimeout)
            ->withOptions($this->requestOptions())
            ->throw();
    }

    /** @return array<string, mixed> */
    protected function requestOptions(): array
    {
        return [
            'verify' => $this->verifySsl,
            // Keep Guzzle's transport defaults instead of Laravel's overrides.
            'connect_timeout' => null,
            'crypto_method' => null,
            'cookies' => false,
            'on_stats' => function (TransferStats $stats) {
                $this->transferStats = $stats;
            },
            ...$this->proxy === null ? [] : ['proxy' => $this->proxy],
            ...$this->cert === null ? [] : ['cert' => [$this->cert, $this->certPassphrase]],
            ...$this->sslKey === null ? [] : ['ssl_key' => [$this->sslKey, $this->sslKeyPassphrase]],
        ];
    }

    protected function shouldBeRemovedFromQueue(): bool
    {
        return false;
    }

    protected function addTimestampToHeaders(): void
    {
        $timestampHeader = config('webhook-server.timestamp_header_name');

        $this->headers[$timestampHeader] = (string) now()->timestamp;
    }

    protected function isGetRequest(): bool
    {
        return strtoupper($this->httpVerb) === 'GET';
    }

    protected function generateBody(): string
    {
        if ($this->outputType !== 'RAW') {
            return json_encode($this->payload, JSON_THROW_ON_ERROR);
        }

        if (! is_string($this->payload)) {
            throw new UnexpectedValueException('The raw webhook payload must be a string.');
        }

        return $this->payload;
    }

    /** @param class-string<WebhookCallEvent> $eventClass */
    private function dispatchEvent(string $eventClass): void
    {
        event(new $eventClass(
            $this->httpVerb,
            $this->getWebhookUrl(),
            $this->payload,
            $this->headers,
            $this->meta,
            $this->tags,
            $this->attempts(),
            $this->response,
            $this->errorType,
            $this->errorMessage,
            $this->uuid,
            $this->transferStats
        ));
    }

    public function failed(Throwable $exception): void
    {
        if ($this->throwExceptionOnFailure) {
            throw $exception;
        }
    }
}
