<?php

namespace Spatie\WebhookServer\Tests;

use Illuminate\Support\Facades\Queue;
use Spatie\WebhookServer\Exceptions\CouldNotCallWebhook;
use Spatie\WebhookServer\Exceptions\InvalidBackoffStrategy;
use Spatie\WebhookServer\Exceptions\InvalidSigner;
use Spatie\WebhookServer\Webhook;

class WebhookTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /** @test */
    public function it_will_throw_an_exception_when_calling_a_webhook_without_proving_an_url()
    {
        $this->expectException(CouldNotCallWebhook::class);

        Webhook::create()->call();
    }

    /** @test */
    public function it_will_throw_an_exception_when_no_secret_has_been_set()
    {
        $this->expectException(CouldNotCallWebhook::class);

        Webhook::create()->url('https://localhost')->call();

    }

    /** @test */
    public function it_will_throw_an_exception_when_using_an_invalid_backoff_strategy()
    {
        $this->expectException(InvalidBackoffStrategy::class);

        Webhook::create()->useBackoffStrategy(static::class);
    }

    /** @test */
    public function it_will_throw_and_exception_when_using_an_invalid_signer()
    {
        $this->expectException(InvalidSigner::class);

        Webhook::create()->signUsing(static::class);
    }
}
