<?php

namespace Spatie\WebhookServer\Tests;

use GuzzleHttp\Client;
use Spatie\WebhookServer\Tests\TestClasses\TestClient;
use Spatie\WebhookServer\Webhook;

class CallWebhookJobTest
{
    public function bla(): void
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
        dd('in test');

        Webhook::create()
            ->url('https://example.com/webhooks')
            ->signUsing('abc')
            ->payload(['a' => 1])
            ->call();
    }
}

