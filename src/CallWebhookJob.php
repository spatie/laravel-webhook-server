<?php

namespace Spatie\WebhookServer;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;

class CallWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string */
    public $webhookUrl;

    /** @var string */
    public $httpVerb;

    /** @var int */
    public $tries;

    /** @var int */
    public $timeout;

    /** @var string */
    public $backoffStrategyClass;

    /** @var string */
    public $signerClass;

    /** @var array */
    public $headers;

    /** @var bool */
    public $verifySsl;

    /** @var string */
    public $queue;

    /** @var array */
    public $payload;

    public function handle()
    {
        $client = app(Client::class);

        $httpVerb = $this->httpVerb;

        try {
            $response = $client->$httpVerb($this->webhookUrl, [
                'timeout' => $this->timeout,
                'body' => json_encode($this->payload),
                'verify' => $this->verifySsl,
                'headers' => $this->headers,
            ]);

            if (!Str::startsWith($response->getStatusCode(), 2)) {
                throw new Exception('Webhook call failed');
            }
        } catch (Exception $exception) {
            /** @var \Spatie\WebhookServer\BackoffStrategy\BackoffStrategy $backoffStrategry */
            $backoffStrategy = app($this->backoffStrategyClass);

            $waitInSeconds = $backoffStrategy->waitInSecondsAfterAttempt($this->attempt);

            // TODO: add event
            $this->release($waitInSeconds);
        }

        if ($this->attempts() >= 3) {
            //TODO: add event
            $this->delete();
        }
    }

    // TODO: add support for tags
}

