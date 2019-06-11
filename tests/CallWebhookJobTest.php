<?php

namespace Spatie\WebhookServer\Tests;

use GuzzleHttp\Client;
use Spatie\WebhookServer\Tests\TestClasses\TestClient;
use Spatie\WebhookServer\Webhook;

class CallWebhookJobTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $testClient = new TestClient();

        app()->bind(Client::class, function () use ($testClient) {
            return $testClient;
        });
    }

    /** @test */
    public function it_tests()
    {
        Webhook::create()
            ->url('https://example.com/webhooks')
            ->useSecret('abc')
            ->payload(['a' => 1])
            ->call();
    }
}

