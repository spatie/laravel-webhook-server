<?php

use Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy;

it('can return the wait in seconds after a certain attempts', function () {
    $strategy = new ExponentialBackoffStrategy();

    expect([
        $strategy->waitInSecondsAfterAttempt(1),
        $strategy->waitInSecondsAfterAttempt(2),
        $strategy->waitInSecondsAfterAttempt(3),
        $strategy->waitInSecondsAfterAttempt(4),
        $strategy->waitInSecondsAfterAttempt(5),
        $strategy->waitInSecondsAfterAttempt(6),
        $strategy->waitInSecondsAfterAttempt(7),
    ])->sequence(10, 100, 1000, 10000, 100000, 100000, 100000);
});
