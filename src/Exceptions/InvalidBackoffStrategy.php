<?php

namespace Spatie\WebhookServer\Exceptions;

use LogicException;
use Spatie\WebhookServer\BackoffStrategy\BackoffStrategy;

/** @phpstan-consistent-constructor */
class InvalidBackoffStrategy extends LogicException
{
    public static function doesNotExtendBackoffStrategy(string $invalidBackoffStrategyClass): self
    {
        $backoffStrategyInterface = BackoffStrategy::class;

        return new static("`{$invalidBackoffStrategyClass}` is not a valid backoff strategy class because it does not implement `$backoffStrategyInterface`");
    }
}
