<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

use function Pest\Laravel\artisan;
use function Pest\Laravel\mock;

use Psr\Http\Message\RequestInterface;
use Spatie\TestTime\TestTime;
use Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
use Spatie\WebhookServer\Tests\TestClasses\InvalidRawPayloadWebhookJob;
use Spatie\WebhookServer\Tests\TestClasses\RedirectTestWebhookJob;
use Spatie\WebhookServer\WebhookCall;

beforeEach(function () {
    Event::fake();
});

function baseWebhook(): WebhookCall
{
    return WebhookCall::create()
        ->url('https://example.com/webhooks')
        ->useSecret('abc')
        ->payload(['a' => 1]);
}

it('can make a webhook call', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->dispatch();

    artisan('queue:work --once --sleep=0');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://example.com/webhooks'
        && $request->body() === json_encode(['a' => 1])
        && $request->hasHeaders([
            'Content-Type' => 'application/json',
            'Signature' => '1f14a62b15ba5095326d6c75c3e2e6b462dd71e1c4b7fbdac0f32309adb7be5f',
        ]));

    expect($this->sentOptions())->toMatchArray([
        'timeout' => 3,
        'verify' => true,
        'cookies' => false,
    ])->not->toHaveKey('connect_timeout')
        ->not->toHaveKey('crypto_method');
});

it('can make a synchronous webhook call', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->dispatchSync();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://example.com/webhooks'
        && $request->body() === json_encode(['a' => 1]));
});

it('can use a different HTTP verb', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->useHttpVerb('put')->dispatch();

    artisan('queue:work --once --sleep=0');

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT');
});

it('uses query parameters when the http verb is get', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->useHttpVerb('get')->dispatch();

    artisan('queue:work --once --sleep=0');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://example.com/webhooks?a=1'
        && $request->body() === '');
});

it('can add extra headers', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()
        ->withHeaders([
            'header1' => 'value1',
            'header2' => 'value2',
        ])
        ->dispatch();

    artisan('queue:work --once --sleep=0');

    Http::assertSent(fn (Request $request) => $request->hasHeaders([
        'Content-Type' => 'application/json',
        'Signature' => '1f14a62b15ba5095326d6c75c3e2e6b462dd71e1c4b7fbdac0f32309adb7be5f',
        'header1' => 'value1',
        'header2' => 'value2',
    ]));
});

it('will not set a signature header when the request should not be signed', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->doNotSign()->dispatch();

    artisan('queue:work --once --sleep=0');

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('Signature'));
});

it('can disable verifying SSL', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->doNotVerifySsl()->dispatch();

    artisan('queue:work --once --sleep=0');

    expect($this->sentOptions()['verify'])->toBeFalse();
});

it('will use mutual TLS without passphrases', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->mutualTls('foobar', 'barfoo')->dispatch();

    artisan('queue:work --once --sleep=0');

    expect($this->sentOptions())->toMatchArray([
        'cert' => ['foobar', null],
        'ssl_key' => ['barfoo', null],
    ]);
});

it('will use mutual TLS with passphrases', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()
        ->mutualTls('foobar', 'barfoo', 'foobarpassword', 'barfoopassword')
        ->dispatch();

    artisan('queue:work --once --sleep=0');

    expect($this->sentOptions())->toMatchArray([
        'cert' => ['foobar', 'foobarpassword'],
        'ssl_key' => ['barfoo', 'barfoopassword'],
    ]);
});

it('will use mutual TLS with a certificate authority', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()
        ->mutualTls('foobar', 'barfoo')
        ->verifySsl('foofoo')
        ->dispatch();

    artisan('queue:work --once --sleep=0');

    expect($this->sentOptions())->toMatchArray([
        'cert' => ['foobar', null],
        'ssl_key' => ['barfoo', null],
        'verify' => 'foofoo',
    ]);
});

it('will use a proxy', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->useProxy('https://proxy.test')->dispatch();

    artisan('queue:work --once --sleep=0');

    expect($this->sentOptions()['proxy'])->toBe('https://proxy.test');
});

it('will use a proxy array', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()
        ->useProxy([
            'http' => 'http://proxy.test',
            'https' => 'https://proxy.test',
        ])
        ->dispatch();

    artisan('queue:work --once --sleep=0');

    expect($this->sentOptions()['proxy'])->toBe([
        'http' => 'http://proxy.test',
        'https' => 'https://proxy.test',
    ]);
});

it('passes the transfer stats to the events', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->dispatch();

    artisan('queue:work --once --sleep=0');

    Event::assertDispatched(
        WebhookCallSucceededEvent::class,
        fn (WebhookCallSucceededEvent $event) => $event->transferStats?->getRequest()->getUri()->getHost() === 'example.com',
    );
});

it('follows redirects without accepting cookies', function (int $status, string $method, string $body) {
    Http::preventStrayRequests(false);
    config()->set('webhook-server.webhook_job', RedirectTestWebhookJob::class);

    app()->instance(MockHandler::class, new MockHandler([
        new Response($status, [
            'Location' => 'https://example.com/redirected',
            'Set-Cookie' => 'session=unexpected; Path=/',
        ]),
        fn (RequestInterface $request) => new Response(200, [], json_encode([
            'method' => $request->getMethod(),
            'body' => (string) $request->getBody(),
            'cookie' => $request->getHeaderLine('Cookie'),
        ])),
    ]));

    baseWebhook()->maximumTries(1)->throwExceptionOnFailure()->dispatchSync();

    Event::assertDispatched(WebhookCallSucceededEvent::class, function (WebhookCallSucceededEvent $event) use ($method, $body) {
        expect($event->response?->json())->toBe([
            'method' => $method,
            'body' => $body,
            'cookie' => '',
        ])->and((string) $event->transferStats?->getEffectiveUri())->toBe('https://example.com/redirected');

        return true;
    });
    Event::assertNotDispatched(WebhookCallFailedEvent::class);
})->with([
    [302, 'GET', ''],
    [307, 'POST', '{"a":1}'],
]);

test('by default it will retry 3 times with the exponential backoff strategy', function (int $status) {
    $this->fakeWebhookEndpoint(status: $status);

    baseWebhook()->dispatch();

    mock(ExponentialBackoffStrategy::class, function (MockInterface $mock) {
        $mock->shouldReceive('waitInSecondsAfterAttempt')->withArgs([1])->once()->andReturns(10);
        $mock->shouldReceive('waitInSecondsAfterAttempt')->withArgs([2])->once()->andReturns(100);
    });

    artisan('queue:work --once --sleep=0');
    Event::assertDispatched(WebhookCallFailedEvent::class, 1);

    TestTime::addSeconds(9);
    artisan('queue:work --once --sleep=0');
    Event::assertDispatched(WebhookCallFailedEvent::class, 1);

    TestTime::addSeconds(1);
    artisan('queue:work --once --sleep=0');
    Event::assertDispatched(WebhookCallFailedEvent::class, 2);

    TestTime::addSeconds(100);
    artisan('queue:work --once --sleep=0');
    Event::assertDispatched(WebhookCallFailedEvent::class, 3);
    Event::assertDispatched(FinalWebhookCallFailedEvent::class, 1);
    Http::assertSentCount(3);

    TestTime::addSeconds(1000);
    artisan('queue:work --once --sleep=0');
    Event::assertDispatched(WebhookCallFailedEvent::class, 3);
    Event::assertDispatched(FinalWebhookCallFailedEvent::class, 1);
    Http::assertSentCount(3);
})->with([302, 422, 500]);

it('only considers a 2xx response successful', function (int $status, bool $successful) {
    $this->fakeWebhookEndpoint(status: $status);

    baseWebhook()->maximumTries(1)->dispatch();

    artisan('queue:work --once --sleep=0');

    if ($successful) {
        Event::assertDispatched(
            WebhookCallSucceededEvent::class,
            fn (WebhookCallSucceededEvent $event) => $event->response?->status() === $status,
        );
        Event::assertNotDispatched(WebhookCallFailedEvent::class);
        Event::assertNotDispatched(FinalWebhookCallFailedEvent::class);

        return;
    }

    Event::assertDispatched(
        WebhookCallFailedEvent::class,
        fn (WebhookCallFailedEvent $event) => $event->response?->status() === $status,
    );
    Event::assertDispatched(
        FinalWebhookCallFailedEvent::class,
        fn (FinalWebhookCallFailedEvent $event) => $event->response?->status() === $status,
    );
    Event::assertNotDispatched(WebhookCallSucceededEvent::class);
})->with([
    [200, true],
    [204, true],
    [299, true],
    [300, false],
    [302, false],
    [304, false],
    [399, false],
    [400, false],
    [500, false],
]);

it('sets the response and error fields when the remote app responds with an error', function (int $status) {
    $this->fakeWebhookEndpoint(status: $status);

    baseWebhook()->dispatch();

    artisan('queue:work --once --sleep=0');

    Event::assertDispatched(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) use ($status) {
        expect($event->response?->status())->toBe($status)
            ->and($event->errorType)->toBe(RequestException::class)
            ->and($event->errorMessage)->toContain((string) $status);

        return true;
    });
})->with([422, 500]);

it('sets the error fields on connection failure', function () {
    Http::fake(['*' => Http::failedConnection()]);

    baseWebhook()->dispatch();

    artisan('queue:work --once --sleep=0');

    Event::assertDispatched(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) {
        expect($event->response)->toBeNull()
            ->and($event->errorType)->toBe(ConnectionException::class)
            ->and($event->errorMessage)->not->toBeEmpty();

        return true;
    });
});

it('generates a job failed event if an exception throws and throw exception on failure config is set', function () {
    Http::fake(['*' => Http::failedConnection()]);

    baseWebhook()->maximumTries(1)->throwExceptionOnFailure()->dispatch();

    artisan('queue:work --once --sleep=0');

    Event::assertDispatched(JobFailed::class, function (JobFailed $event) {
        expect($event->exception)->toBeInstanceOf(ConnectionException::class);

        return true;
    });
});

it('sends raw body data if rawBody is set', function () {
    $this->fakeWebhookEndpoint();

    WebhookCall::create()
        ->url('https://example.com/webhooks')
        ->sendRawBody('<xml>anotherOption</xml>')
        ->doNotSign()
        ->dispatch();

    artisan('queue:work --once --sleep=0');

    Http::assertSent(fn (Request $request) => $request->body() === '<xml>anotherOption</xml>');
});

it('reports an invalid raw payload through the webhook failure event', function () {
    config()->set('webhook-server.webhook_job', InvalidRawPayloadWebhookJob::class);

    baseWebhook()->maximumTries(1)->dispatch();

    artisan('queue:work --once --sleep=0');

    Event::assertDispatched(
        FinalWebhookCallFailedEvent::class,
        fn (FinalWebhookCallFailedEvent $event) => $event->errorType === UnexpectedValueException::class
            && $event->errorMessage === 'The raw webhook payload must be a string.'
            && $event->payload === ['a' => 1],
    );
    Event::assertNotDispatched(WebhookCallSucceededEvent::class);
    Http::assertNothingSent();
});

it('preserves configured content type headers', function (array $headers) {
    $this->fakeWebhookEndpoint();

    config()->set('webhook-server.headers', $headers);

    WebhookCall::create()
        ->url('https://example.com/webhooks')
        ->sendRawBody('<xml>anotherOption</xml>')
        ->doNotSign()
        ->dispatch();

    artisan('queue:work --once --sleep=0');

    Http::assertSent(function (Request $request) use ($headers) {
        expect($request->body())->toBe('<xml>anotherOption</xml>');

        if ($headers === []) {
            expect($request->hasHeader('Content-Type'))->toBeFalse();

            return true;
        }

        expect($request->header('Content-Type'))->toBe(['application/xml']);

        return true;
    });
    Event::assertDispatched(WebhookCallSucceededEvent::class);
})->with([
    'canonical' => [['Content-Type' => 'application/xml']],
    'lowercase' => [['content-type' => 'application/xml']],
    'array value' => [['cOnTeNt-TyPe' => ['application/xml']]],
    'no content type' => [[]],
]);

it('sends raw body data in the event if rawBody is set', function () {
    Http::fake(['*' => Http::failedConnection()]);

    WebhookCall::create()
        ->url('https://example.com/webhooks')
        ->sendRawBody('<xml>anotherOption</xml>')
        ->doNotSign()
        ->dispatch();

    artisan('queue:work --once --sleep=0');

    Event::assertDispatched(
        WebhookCallFailedEvent::class,
        fn (WebhookCallFailedEvent $event) => $event->payload === '<xml>anotherOption</xml>',
    );
});

it('sets the timestamp header when using the timestamp option', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->useTimestamp()->dispatch();

    artisan('queue:work --once --sleep=0');

    $timestamp = (string) TestTime::now()->getTimestamp();

    Http::assertSent(fn (Request $request) => $request->header('Timestamp') === [$timestamp]);

    Event::assertDispatched(
        WebhookCallSucceededEvent::class,
        fn (WebhookCallSucceededEvent $event) => $event->headers['Timestamp'] === $timestamp,
    );
});

it('allows configuring or disabling the delivery ID header', function (?string $header, bool $perCall) {
    $this->fakeWebhookEndpoint();

    if (! $perCall) {
        config()->set('webhook-server.webhook_id_header_name', $header);
    }

    $webhook = baseWebhook()->uuid('delivery-42')->doNotSign();

    if ($perCall) {
        $webhook->withWebhookIdHeader($header);
    }

    $webhook->dispatch();

    artisan('queue:work --once --sleep=0');

    Http::assertSent(function (Request $request) use ($header) {
        expect($request->hasHeader('Webhook-Id'))->toBeFalse();

        if ($header !== null) {
            expect($request->header($header))->toBe(['delivery-42']);
        }

        return true;
    });
})->with([null, 'X-Delivery-Id'])->with([true, false]);

it('preserves an explicitly supplied delivery ID header regardless of casing', function () {
    $this->fakeWebhookEndpoint();

    baseWebhook()->withHeaders(['wEbHoOk-Id' => 'external-id'])->dispatchSync();

    Http::assertSent(fn (Request $request) => $request->header('Webhook-Id') === ['external-id']);
});
