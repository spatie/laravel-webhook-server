<?php

namespace Spatie\WebhookServer\BackoffStrategy;

class ExponentialBackoffStrategy implements BackoffStrategy
{
    public function waitInSecondsAfterAttempt(int $attempt): int
    {
        return min(10 ** $attempt, 100000);
    }
}
