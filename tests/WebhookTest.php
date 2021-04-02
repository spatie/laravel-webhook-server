<?php

namespace Spatie\WebhookServer\Tests;

use Illuminate\Support\Facades\Queue;
use Spatie\WebhookServer\CallWebhookJob;
use Spatie\WebhookServer\Exceptions\CouldNotCallWebhook;
use Spatie\WebhookServer\Exceptions\InvalidBackoffStrategy;
use Spatie\WebhookServer\Exceptions\InvalidSigner;
use Spatie\WebhookServer\WebhookCall;

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
    public function it_can_keep_default_config_headers_and_set_new_ones()
    {
        $url = 'https://localhost';

        WebhookCall::create()->url($url)
            ->withHeaders(['User-Agent' => 'Spatie/Laravel-Webhook-Server'])
            ->useSecret('123')
            ->dispatch()
        ;

        Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) use ($url) {
            $config = config('webhook-server');

            $this->assertArrayHasKey('Content-Type', $job->headers);
            $this->assertArrayHasKey('User-Agent', $job->headers);

            return true;
        });
    }

    /** @test */
    public function it_can_override_default_config_headers()
    {
        $url = 'https://localhost';

        WebhookCall::create()->url($url)
            ->withHeaders(['Content-Type' => 'text/plain'])
            ->useSecret('123')
            ->dispatch()
        ;

        Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) use ($url) {
            $config = config('webhook-server');

            $this->assertArrayHasKey('Content-Type', $job->headers);
            $this->assertEquals('text/plain', $job->headers['Content-Type']);

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
    public function it_will_not_throw_an_exception_if_there_is_not_secret_and_the_request_should_not_be_signed()
    {
        WebhookCall::create()->doNotSign()->url('https://localhost')->dispatch();

        $this->assertTrue(true);
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

    /** @test */
    public function it_can_get_the_uuid_property()
    {
        $webhookCall = WebhookCall::create()->uuid('my-unique-identifier');

        $this->assertIsString($webhookCall->getUuid());
        $this->assertSame('my-unique-identifier', $webhookCall->getUuid());
    }
}
