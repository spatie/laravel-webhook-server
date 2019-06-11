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
    public function it_can_make_webhook_call()
    {
        $this->baseWebhook()->call();

        $this
            ->testClient
            ->assertRequestsMade($this->baseRequestMade());
    }

    protected function baseWebhook(): Webhook
    {
        return Webhook::create()
            ->url('https://example.com/webhooks')
            ->useSecret('abc')
            ->payload(['a' => 1]);
    }

    protected function baseRequestMade(): array
    {
        return [
            [
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
            ]
        ];
    }
}

