<?php

namespace Spatie\WebhookServer\BackoffStrategy;

class ExponentialBackoffStrategy implements BackoffStrategy
{
    public function waitInSecondsAfterAttempt(int $attempt): int
    {
        return 10 ** $attempt;
    }
}

