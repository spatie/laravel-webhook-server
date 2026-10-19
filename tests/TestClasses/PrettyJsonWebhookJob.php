<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use Spatie\WebhookServer\CallWebhookJob;

class PrettyJsonWebhookJob extends CallWebhookJob
{
    protected function generateBody(): string
    {
        return json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
