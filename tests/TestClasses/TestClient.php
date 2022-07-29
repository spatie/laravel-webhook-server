<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TestClient implements ClientInterface
{
    protected array $requests = [];

    protected int $useResponseCode = 200;

    protected bool $throwRequestException = false;

    protected bool $throwConnectionException = false;

    public function request(string $method, $url = '', array $options = []): ResponseInterface
    {
        $this->requests[] = compact('method', 'url', 'options');

        if ($this->throwRequestException) {
            throw new RequestException(
                'Request failed exception',
                new Request($method, $url),
                new Response(500)
            );
        }

        if ($this->throwConnectionException) {
            throw new ConnectException(
                'Request timeout',
                new Request($method, $url),
            );
        }

        return new Response($this->useResponseCode);
    }

    public function assertRequestCount(int $expectedCount)
    {
        Assert::assertCount($expectedCount, $this->requests);

        return $this;
    }

    public function assertRequestsMade(array $expectedRequests)
    {
        $this->assertRequestCount(count($expectedRequests));

        foreach ($expectedRequests as $index => $expectedRequest) {
            foreach ($expectedRequest as $name => $value) {
                Assert::assertEquals($value, $this->requests[$index][$name]);
            }
        }
    }

    public function letEveryRequestFail()
    {
        $this->useResponseCode = 500;
    }

    public function throwRequestException()
    {
        $this->throwRequestException = true;
    }

    public function throwConnectionException()
    {
        $this->throwConnectionException = true;
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        throw new \BadMethodCallException('Not meant to be used yet.');
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        throw new \BadMethodCallException('Not meant to be used yet.');
    }

    public function requestAsync(string $method, $uri, array $options = []): PromiseInterface
    {
        throw new \BadMethodCallException('Not meant to be used yet.');
    }

    public function getConfig(?string $option = null)
    {
        throw new \BadMethodCallException('Not meant to be used yet.');
    }
}
