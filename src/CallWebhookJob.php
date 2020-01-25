<?php

namespace Spatie\WebhookServer;

use GuzzleHttp\Psr7\Response;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;

class CallWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $webhookUrl = null;

    public string $httpVerb;

    public int $tries;

    public int $requestTimeout;

    public string $backoffStrategyClass;

    public ?string $signerClass = null;

    public array $headers = [];

    public bool $verifySsl;

    /** @var string */
    public  $queue;

    public array $payload = [];

    public array $meta = [];

    public array $tags = [];

    private ?Response $response = null;

    private ?string $errorType = null;

    private ?string $errorMessage = null;

    public function handle()
    {
        /** @var \GuzzleHttp\Client $client */
        $client = app(Client::class);

        $lastAttempt = $this->attempts() >= $this->tries;

        try {
            $this->response = $client->request($this->httpVerb, $this->webhookUrl, [
                'timeout' => $this->requestTimeout,
                'body' => json_encode($this->payload),
                'verify' => $this->verifySsl,
                'headers' => $this->headers,
            ]);

            if (! Str::startsWith($this->response->getStatusCode(), 2)) {
                throw new Exception('Webhook call failed');
            }

            $this->dispatchEvent(WebhookCallSucceededEvent::class);

            return;
        } catch (Exception $exception) {
            report($exception);

            if ($exception instanceof RequestException) {
                $this->response = $exception->getResponse();
                $this->errorType = get_class($exception);
                $this->errorMessage = $exception->getMessage();
            }

            if (! $lastAttempt) {
                /** @var \Spatie\WebhookServer\BackoffStrategy\BackoffStrategy $backoffStrategy */
                $backoffStrategy = app($this->backoffStrategyClass);

                $waitInSeconds = $backoffStrategy->waitInSecondsAfterAttempt($this->attempts());

                $this->release($waitInSeconds);
            }

            $this->dispatchEvent(WebhookCallFailedEvent::class);
        }

        if ($lastAttempt) {
            $this->dispatchEvent(FinalWebhookCallFailedEvent::class);

            $this->delete();
        }
    }

    public function tags()
    {
        return $this->tags;
    }

    public function getResponse()
    {
        return $this->response;
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
            $this->errorMessage
        ));
    }
}
