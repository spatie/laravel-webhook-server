<?php

namespace Spatie\WebhookServer\Tests;

use Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy;

class ExponentialBackoffStrategyTest extends TestCase
{
    /** @test */
    public function it_can_return_the_wait_in_seconds_after_a_certain_attemps()
    {
        $strategy = new ExponentialBackoffStrategy();

        $this->assertEquals(10, $strategy->waitInSecondsAfterAttempt(1));
        $this->assertEquals(100, $strategy->waitInSecondsAfterAttempt(2));
        $this->assertEquals(1000, $strategy->waitInSecondsAfterAttempt(3));
    }
}

