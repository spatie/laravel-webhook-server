<?php

namespace Spatie\WebhookServer\Exceptions;

use LogicException;
use Spatie\WebhookServer\CallWebhookJob;

class InvalidWebhookJob extends LogicException
{
    public static function doesNotExtendCallWebhookJob(string $invalidWebhookJobClass): self
    {
        $callWebhookJob = CallWebhookJob::class;

        return new static("`{$invalidWebhookJobClass}` is not a valid webhook job class because it does not extend `$callWebhookJob`");
    }
}
