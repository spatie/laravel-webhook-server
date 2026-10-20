<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use GuzzleHttp\Handler\MockHandler;
use Illuminate\Http\Client\PendingRequest;
use Spatie\WebhookServer\CallWebhookJob;

class RedirectTestWebhookJob extends CallWebhookJob
{
    protected function createRequest(): PendingRequest
    {
        return parent::createRequest()->setHandler(app(MockHandler::class));
    }
}
