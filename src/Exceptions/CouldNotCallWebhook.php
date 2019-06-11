<?php

namespace Spatie\WebhookServer\Exceptions;

use Exception;

class CouldNotCallWebhook extends Exception
{
    public static function urlNotSet()
    {
        return new static("Could not call the webhook because the url has not been set.");
    }

    public static function secretNotSet()
    {
        return new static("Could not call the webhook because no secret has been set.");
    }
}

