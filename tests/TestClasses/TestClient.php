<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

class TestClient
{
    public $requests = [];

    public function post(string $url, array $payload)
    {
        $this->requests[] = compact('url', 'payload');

        return new TestResponse();
    }
}

