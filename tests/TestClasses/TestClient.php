<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TestClient
{
    protected array $requests = [];

    protected bool $throwConnectionException = false;

    public function assertRequestCount(int $expectedCount)
    {
        Http::assertSentCount($expectedCount);

        return $this;
    }

    public function assertRequestsMade(array $expectedRequests)
    {
        $this->assertRequestCount(count($expectedRequests));
        foreach ($expectedRequests as $index => $expectedRequest) {
            Http::assertSent(function (Request $request) use ($expectedRequest) {
                return $this->assertUrl($request, $expectedRequest)  &&
                    $request->hasHeaders($expectedRequest['options']['headers']) &&
                    $this->assertData($request, $expectedRequest) &&
                    $request->method() === strtoupper($expectedRequest['method']);
            });
        }
    }

    public function throwConnectionException()
    {
        $this->throwConnectionException = true;
    }

    private function assertUrl(Request $request, $expectedRequest): bool
    {
        if ($request->method() === 'GET') {
            return Str::of($request->url())
                ->contains(http_build_query(
                    $expectedRequest['options']['query'] ?? []));
        }
        return $request->url() === $expectedRequest['url'];
    }

    private function assertData(Request $request, $expectedRequest): bool
    {
        $data = empty($expectedRequest['options']['body']) ? '' : $expectedRequest['options']['body'];
        if ($request->method() === 'GET') {
            $data = $expectedRequest['options']['query'];
            return $request->data() === $data;
        }
        return $request->data()['body'] ===  $data;
    }
}
