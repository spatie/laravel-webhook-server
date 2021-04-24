<?php

namespace Spatie\WebhookServer\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Spatie\TestTime\TestTime;
use Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Tests\TestClasses\TestClient;
use Spatie\WebhookServer\WebhookCall;

class CallWebhookJobTest extends TestCase
{
    private TestClient $testClient;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->testClient = new TestClient();

        app()->bind(Client::class, function () {
            return $this->testClient;
        });
    }

    /** @test */
    public function it_can_make_a_webhook_call()
    {
        $this->baseWebhook()->dispatch();

        $this->artisan('queue:work --once');

        $this
            ->testClient
            ->assertRequestsMade([$this->baseRequest()]);
    }

    /** @test */
    public function it_can_make_a_legacy_synchronous_webhook_call()
    {
        $this->baseWebhook()->dispatchNow();

        $this
            ->testClient
            ->assertRequestsMade([$this->baseRequest()]);
    }

    /** @test */
    public function it_can_make_a_synchronous_webhook_call()
    {
        $this->baseWebhook()->dispatchSync();

        $this
            ->testClient
            ->assertRequestsMade([$this->baseRequest()]);
    }

    /** @test */
    public function it_can_use_a_different_http_verb()
    {
        $this
            ->baseWebhook()
            ->useHttpVerb('put')
            ->dispatch();

        $baseResponse = $this->baseRequest(['method' => 'put']);

        $this->artisan('queue:work --once');

        $this
            ->testClient
            ->assertRequestsMade([$baseResponse]);
    }

    /** @test */
    public function it_uses_query_option_when_http_verb_is_get()
    {
        $this
            ->baseWebhook()
            ->useHttpVerb('get')
            ->dispatch();

        $baseResponse = $this->baseGetRequest();

        $this->artisan('queue:work --once');

        $this
            ->testClient
            ->assertRequestsMade([$baseResponse]);
    }

    /** @test */
    public function it_can_add_extra_headers()
    {
        $extraHeaders = [
            'Content-Type' => 'application/json',
            'header1' => 'value1',
            'headers2' => 'value2',
        ];

        $this->baseWebhook()
            ->withHeaders($extraHeaders)
            ->dispatch();

        $baseRequest = $this->baseRequest();

        $baseRequest['options']['headers'] = array_merge(
            $baseRequest['options']['headers'],
            $extraHeaders
        );

        $this->artisan('queue:work --once');

        $this
            ->testClient
            ->assertRequestsMade([$baseRequest]);
    }

    /** @test */
    public function it_will_not_set_a_signature_header_when_the_request_should_not_be_signed()
    {
        $this->baseWebhook()
            ->doNotSign()
            ->dispatch();

        $baseRequest = $this->baseRequest();

        unset($baseRequest['options']['headers']['Signature']);

        $this->artisan('queue:work --once');

        $this
            ->testClient
            ->assertRequestsMade([$baseRequest]);
    }

    /** @test */
    public function it_can_disable_verifying_ssl()
    {
        $this->baseWebhook()->doNotVerifySsl()->dispatch();

        $baseRequest = $this->baseRequest();
        $baseRequest['options']['verify'] = false;

        $this->artisan('queue:work --once');

        $this
            ->testClient
            ->assertRequestsMade([$baseRequest]);
    }

    /** @test */
    public function by_default_it_will_retry_3_times_with_the_exponential_backoff_strategy()
    {
        $this->testClient->letEveryRequestFail();

        $this->baseWebhook()->dispatch();

        $this->mock(ExponentialBackoffStrategy::class, function (MockInterface $mock) {
            $mock->shouldReceive('waitInSecondsAfterAttempt')->withArgs([1])->once()->andReturns(10);
            $mock->shouldReceive('waitInSecondsAfterAttempt')->withArgs([2])->once()->andReturns(100);
            $mock->shouldReceive('waitInSecondsAfterAttempt')->withArgs([3])->never();

            return $mock;
        });

        $this->artisan('queue:work --once');
        Event::assertDispatched(WebhookCallFailedEvent::class, 1);

        TestTime::addSeconds(9);
        $this->artisan('queue:work --once');
        Event::assertDispatched(WebhookCallFailedEvent::class, 1);

        TestTime::addSeconds(1);
        $this->artisan('queue:work --once');
        Event::assertDispatched(WebhookCallFailedEvent::class, 2);

        TestTime::addSeconds(100);
        $this->artisan('queue:work --once');
        Event::assertDispatched(WebhookCallFailedEvent::class, 3);
        Event::assertDispatched(FinalWebhookCallFailedEvent::class, 1);
        $this->testClient->assertRequestCount(3);

        TestTime::addSeconds(1000);
        $this->artisan('queue:work --once');
        Event::assertDispatched(WebhookCallFailedEvent::class, 3);
        Event::assertDispatched(FinalWebhookCallFailedEvent::class, 1);
        $this->testClient->assertRequestCount(3);
    }

    /** @test */
    public function it_sets_the_response_field_on_request_failure()
    {
        $this->testClient->throwRequestException();

        $this->baseWebhook()->dispatch();

        $this->artisan('queue:work --once');
        Event::assertDispatched(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) {
            $this->assertNotNull($event->response);

            return true;
        });
    }

    /** @test */
    public function it_sets_the_error_fields_on_connection_failure()
    {
        $this->testClient->throwConnectionException();

        $this->baseWebhook()->dispatch();

        $this->artisan('queue:work --once');

        Event::assertDispatched(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) {
            $this->assertNotNull($event->errorType);
            $this->assertNotNull($event->errorMessage);

            return true;
        });
    }

    protected function baseWebhook(): WebhookCall
    {
        return WebhookCall::create()
            ->url('https://example.com/webhooks')
            ->useSecret('abc')
            ->payload(['a' => 1]);
    }

    protected function baseRequest(array $overrides = []): array
    {
        $defaultProperties = [
            'method' => 'post',
            'url' => 'https://example.com/webhooks',
            'options' => [
                'timeout' => 3,
                'body' => json_encode(['a' => 1]),
                'verify' => true,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Signature' => '1f14a62b15ba5095326d6c75c3e2e6b462dd71e1c4b7fbdac0f32309adb7be5f',
                ],
                'on_stats' => function (TransferStats $stats) {
                },
            ],
        ];

        return array_merge($defaultProperties, $overrides);
    }

    protected function baseGetRequest(array $overrides = []): array
    {
        $defaultProperties = [
            'method' => 'get',
            'url' => 'https://example.com/webhooks',
            'options' => [
                'timeout' => 3,
                'query' => ['a' => 1],
                'verify' => true,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Signature' => '1f14a62b15ba5095326d6c75c3e2e6b462dd71e1c4b7fbdac0f32309adb7be5f',
                ],
                'on_stats' => function (TransferStats $stats) {
                },
            ],
        ];

        return array_merge($defaultProperties, $overrides);
    }
}
