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
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
use Throwable;

class CallWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $webhookUrl = null;

    public string $httpVerb;

    public string|array|null $proxy = null;

    public int $tries;

    public int $requestTimeout;

    public ?string $cert = null;

    public ?string $certPassphrase = null;

    public ?string $sslKey = null;

    public ?string $sslKeyPassphrase = null;

    public string $backoffStrategyClass;

    public ?string $signerClass = null;

    public array $headers = [];

    public string|bool $verifySsl;

    public bool $useTimestamp = false;

    public bool $throwExceptionOnFailure;

    /** @var string|null */
    public $queue = null;

    public array|string $payload = [];

    public array $meta = [];

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
                $this->webhookUrl,
                $this->isGetRequest() ? ['query' => $this->payload] : [],
            );

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

    public function tags(): array
    {
        return $this->tags;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    protected function createRequest(): PendingRequest
    {
        $request = Http::withHeaders($this->headers)
            ->timeout($this->requestTimeout)
            ->withOptions($this->requestOptions())
            ->throw();

        if ($this->isGetRequest()) {
            return $request;
        }

        return $request->withBody($this->generateBody(), $this->contentType());
    }

    protected function requestOptions(): array
    {
        return [
            'verify' => $this->verifySsl,
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

    /**
     * The body is attached through `withBody()`, which always sets a content type. Resolving it
     * from the configured headers keeps a custom content type, such as the one you need when
     * sending a raw XML body, from being overwritten with `application/json`.
     */
    protected function contentType(): string
    {
        foreach ($this->headers as $name => $value) {
            if (strtolower($name) === 'content-type') {
                return $value;
            }
        }

        return 'application/json';
    }

    protected function generateBody(): string
    {
        return match ($this->outputType) {
            'RAW' => $this->payload,
            default => json_encode($this->payload, JSON_THROW_ON_ERROR),
        };
    }

    private function dispatchEvent(string $eventClass): void
    {
        event(new $eventClass(
            $this->httpVerb,
            $this->webhookUrl,
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

    public function failed(Throwable $e): void
    {
        if ($this->throwExceptionOnFailure) {
            throw $e;
        }
    }
}
