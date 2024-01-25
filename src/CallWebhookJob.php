<?php

namespace Spatie\WebhookServer;

use Exception;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\TransferStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\WebhookServer\BackoffStrategy\BackoffStrategy;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

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

    public array $options = [];

    public string|bool $verifySsl;

    public bool $throwExceptionOnFailure;

    /** @var string|null */
    public $queue = null;

    public array|string $payload = [];

    public array $meta = [];

    public array $tags = [];

    public string $uuid = '';

    public string $outputType = "JSON";

    protected ?Response $response = null;

    protected ?string $errorType = null;

    protected ?string $errorMessage = null;

    protected ?TransferStats $transferStats = null;

    public function handle(): void
    {
        $lastAttempt = $this->attempts() >= $this->tries;

        try {
            $body = strtoupper($this->httpVerb) === 'GET'
                ? ['query' => $this->payload]
                : ['body' => $this->generateBody()];

            $this->response = $this->createRequest($body);

            if (! Str::startsWith($this->response->getStatusCode(), 2)) {
                throw new Exception('Webhook call failed');
            }

            $this->dispatchEvent(WebhookCallSucceededEvent::class);

            return;
        } catch (Exception $exception) {
            $this->errorType = get_class($exception);
            $this->errorMessage = $exception->getMessage();

            if ($exception instanceof RequestException) {
                $this->response = $this->toGuzzleResponse($exception->response);
            }

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

    protected function toGuzzleResponse(HttpClientResponse $response): GuzzleResponse
    {
        $psrResponse = $response->toPsrResponse();

        return new GuzzleResponse(
            $psrResponse->getStatusCode(),
            $psrResponse->getHeaders(),
            $psrResponse->getBody(),
            $psrResponse->getProtocolVersion(),
            $psrResponse->getReasonPhrase()
        );
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    protected function createRequest(array $body): Response
    {
        $request = Http::withHeaders($this->headers)
            ->timeout($this->requestTimeout)
            ->unless($this->outputType === 'JSON', function (PendingRequest $request) {
                $request->withHeaders([
                    'Content-Type' => "text/xml;charset=utf-8"
                ]);
            })
            ->unless($this->verifySsl, fn(PendingRequest $request) => $request->withoutVerifying());

        $request->withOptions(array_merge($this->options, [
            is_null($this->proxy) ? [] : ['proxy' => $this->proxy],
            is_null($this->cert) ? [] : ['cert' => [$this->cert, $this->certPassphrase]],
            is_null($this->sslKey) ? [] : ['ssl_key' => [$this->sslKey, $this->sslKeyPassphrase]]
        ]));

        $response = match (strtoupper($this->httpVerb)) {
            'GET'  => $request->get($this->webhookUrl, $body['query']),
            'POST' => $request->post($this->webhookUrl, $body),
            'PUT'  => $request->put($this->webhookUrl, $body),
            'PATCH'  => $request->patch($this->webhookUrl, $body),
        };


        $this->transferStats = $response->transferStats;
        $response->throw();

        return $this->toGuzzleResponse($response);
    }

    protected function shouldBeRemovedFromQueue(): bool
    {
        return false;
    }

    private function dispatchEvent(string $eventClass)
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

    private function generateBody(): string
    {
        return match ($this->outputType) {
            "RAW" => $this->payload,
            default => json_encode($this->payload),
        };
    }
}
