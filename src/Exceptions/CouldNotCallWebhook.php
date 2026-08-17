<?php

namespace Spatie\WebhookServer\Exceptions;

use LogicException;

class CouldNotCallWebhook extends LogicException
{
    public static function urlNotSet(): self
    {
        return new static('Could not call the webhook because the url has not been set.');
    }

    public static function secretNotSet(): self
    {
        return new static('Could not call the webhook because no secret has been set.');
    }
}
