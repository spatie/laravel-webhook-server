<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use Spatie\WebhookServer\CallWebhookJob;

class BinaryBodyWebhookJob extends CallWebhookJob
{
    protected function generateBody(): string
    {
        return "generated\xfe\0body";
    }
}
