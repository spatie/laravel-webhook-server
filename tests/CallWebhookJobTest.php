<?php

namespace Spatie\WebhookServer\Tests;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookServer\Tests\TestClasses\TestClient;
use Spatie\WebhookServer\Webhook;

class CallWebhookJobTest extends TestCase
{
    /** @var \Spatie\WebhookServer\Tests\TestClasses\TestClient */
    private $testClient;

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
        $this->baseWebhook()->call();

        $this
            ->testClient
            ->assertRequestsMade([$this->baseRequest()]);
    }

    /** @test */
    public function it_can_use_a_different_http_verb()
    {
        $this
            ->baseWebhook()
            ->useHttpVerb('get')
            ->call();

        $baseResponse = $this->baseRequest(['method' => 'get']);

        $this
            ->testClient
            ->assertRequestsMade([$baseResponse]);
    }

    /** @test */
    public function it_can_add_extra_headers()
    {
        $extraHeaders = [
            'header1' => 'value1',
            'headers2' => 'value2',
        ];

        $this->baseWebhook()
            ->withHeaders($extraHeaders)
            ->call();

        $baseRequest = $this->baseRequest();

        $baseRequest['options']['headers'] = array_merge(
            $baseRequest['options']['headers'],
            $extraHeaders,
        );

        $this
            ->testClient
            ->assertRequestsMade([$baseRequest]);
    }

    /** @test */
    public function it_can_disable_verifying_ssl()
    {
        $this->baseWebhook()->doNotVerifySsl()->call();

        $baseRequest = $this->baseRequest();
        $baseRequest['options']['verify'] = false;

        $this
            ->testClient
            ->assertRequestsMade([$baseRequest]);
    }

    protected function baseWebhook(): Webhook
    {
        return Webhook::create()
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
                    'Signature' => '1f14a62b15ba5095326d6c75c3e2e6b462dd71e1c4b7fbdac0f32309adb7be5f',
                ],
            ],
        ];

        return array_merge($defaultProperties, $overrides);
    }
}

