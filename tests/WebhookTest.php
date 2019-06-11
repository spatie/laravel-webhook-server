<?php

namespace Spatie\WebhookServer\Tests;

use Illuminate\Support\Facades\Queue;
use Spatie\WebhookServer\Exceptions\InvalidBackoffStrategy;
use Spatie\WebhookServer\Exceptions\InvalidSigner;
use Spatie\WebhookServer\Webhook;
use stdClass;

class WebhookTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
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
