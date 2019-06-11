<?php

namespace Spatie\WebhookServer;

use Spatie\WebhookServer\Exceptions\CouldNotCallWebhook;

class Webhook
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

    public static function create()
    {
        $config = config('webhook-server');

        return (new static())
            ->onQueue($config['queue'])
            ->useHttpVerb($config['http_verb'])
            ->maximumTries($config['tries'])
            ->useBackoffStrategy($config['backoff_strategy'])
            ->timeoutInSeconds($config['timeout_in_seconds'])
            ->signUsing($config['signer'])
            ->withHeaders($config['header'])
            ->verifySsl($config['verify_ssl']);
    }

    public function __construct()
    {
        $this->callWebhookJob = new CallWebhookJob();
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
        $this->callWebhookJob->backoffStrategyClass = $backoffStrategyClass;

        return $this;
    }

    public function timeoutInSeconds(int $timeoutInSeconds)
    {
        $this->callWebhookJob->timeout = $timeoutInSeconds;

        return $this;
    }

    public function signUsing(string $signerClass)
    {
        $this->signer = app($signerClass);

        //TODO: verify is instance of Signer

        return $this;
    }

    public function withHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    public function verifySsl(bool $verifySsl)
    {
        $this->callWebhookJob->verifySsl = $verifySsl;

        return $this;
    }

    public function meta(array $meta)
    {
        $this->callWebhookJob->meta = $meta;

        return $this;
    }

    public function call()
    {
        if (empty($this->url())) {
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
