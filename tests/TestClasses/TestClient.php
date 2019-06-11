<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;

class TestClient
{
    private $requests = [];

    public function request(string $method, string $url, array $options)
    {
        $this->requests[] = compact('method', 'url', 'options');

        return new Response(200);
    }

    public function assertRequestCount(int $expectedCount)
    {
        Assert::assertCount($expectedCount, $this->requests);

        return $this;
    }

    public function assertRequestsMade(array $expectedRequests)
    {
        $this->assertRequestCount(count($expectedRequests));

        foreach($expectedRequests as $index => $expectedRequest) {
            foreach($expectedRequest as $name => $value) {
                Assert::assertEquals($value, $this->requests[$index][$name]);
            }
        }
    }
}

