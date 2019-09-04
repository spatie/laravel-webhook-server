<?php

namespace Spatie\WebhookServer\Tests;

use Illuminate\Support\Facades\Queue;
use Spatie\WebhookServer\WebhookCall;
use Spatie\WebhookServer\CallWebhookJob;
use Spatie\WebhookServer\Exceptions\InvalidSigner;
use Spatie\WebhookServer\Exceptions\CouldNotCallWebhook;
use Spatie\WebhookServer\Exceptions\InvalidBackoffStrategy;

class WebhookTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /** @test */
    public function it_can_dispatch_a_job_that_calls_a_webhook()
    {
        $url = 'https://localhost';

        WebhookCall::create()->url($url)->useSecret('123')->dispatch();

        Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) use ($url) {
            $config = config('webhook-server');

            $this->assertEquals($config['queue'], $job->queue);
            $this->assertEquals($url, $job->webhookUrl);
            $this->assertEquals($config['http_verb'], $job->httpVerb);
            $this->assertEquals($config['tries'], $job->tries);
            $this->assertEquals($config['timeout_in_seconds'], $job->requestTimeout);
            $this->assertEquals($config['backoff_strategy'], $job->backoffStrategyClass);
            $this->assertContains($config['signature_header_name'], array_keys($job->headers));
            $this->assertEquals($config['verify_ssl'], $job->verifySsl);
            $this->assertEquals($config['tags'], $job->tags);

            return true;
        });
    }

    /** @test */
    public function it_will_throw_an_exception_when_calling_a_webhook_without_proving_an_url()
    {
        $this->expectException(CouldNotCallWebhook::class);

        WebhookCall::create()->dispatch();
    }

    /** @test */
    public function it_will_throw_an_exception_when_no_secret_has_been_set()
    {
        $this->expectException(CouldNotCallWebhook::class);

        WebhookCall::create()->url('https://localhost')->dispatch();
    }

    /** @test */
    public function it_will_throw_an_exception_when_using_an_invalid_backoff_strategy()
    {
        $this->expectException(InvalidBackoffStrategy::class);

        WebhookCall::create()->useBackoffStrategy(static::class);
    }

    /** @test */
    public function it_will_throw_and_exception_when_using_an_invalid_signer()
    {
        $this->expectException(InvalidSigner::class);

        WebhookCall::create()->signUsing(static::class);
    }
}
