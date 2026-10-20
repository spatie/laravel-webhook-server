<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use Spatie\WebhookServer\CallWebhookJob;

class InvalidRawPayloadWebhookJob extends CallWebhookJob
{
    public string $outputType = 'RAW';
}
