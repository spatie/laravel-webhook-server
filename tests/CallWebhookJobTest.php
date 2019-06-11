<?php

namespace Spatie\WebhookServer\Tests;

use GuzzleHttp\Client;
use Spatie\WebhookServer\Tests\TestClasses\TestClient;
use Spatie\WebhookServer\Webhook;

class CallWebhookJobTest extends TestCase
{
    /** @var \Spatie\WebhookServer\Tests\TestClasses\TestClient */
    private $testClient;

    public function setUp(): void
    {
        parent::setUp();

        $this->testClient = new TestClient();

        app()->bind(Client::class, function () {
            return $this->testClient;
        });
    }

    /** @test */
    public function it_can_make_webhook_call()
    {
        $url = 'https://example.com/webhooks';
        $payload = ['a' => 1];

        Webhook::create()
            ->url($url)
            ->useSecret('abc')
            ->payload($payload)
            ->call();

        $this->testClient
            ->assertRequestsMade([
                [
                    'method' => 'post',
                    'url' => $url,
                    'options' => [
                        'timeout' => 3,
                        'body' => json_encode($payload),
                        'verify' => true,
                        'headers' => [
                            'Signature' => '1f14a62b15ba5095326d6c75c3e2e6b462dd71e1c4b7fbdac0f32309adb7be5f',
                        ],
                    ],
                ]
            ]);
    }
}

