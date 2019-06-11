<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use Tests\Unit\Services\Webhooks\TestResponse;

class TestClient
{
    public $requests = [];

    public function post(string $url, array $payload)
    {
        $this->requests[] = compact('url', 'payload');

        return new TestResponse();
    }
}

