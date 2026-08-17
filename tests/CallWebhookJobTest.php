<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use function Pest\Laravel\artisan;
use function Pest\Laravel\mock;
use Spatie\TestTime\TestTime;
use Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
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
    ]);
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

test('by default it will retry 3 times with the exponential backoff strategy', function () {
    $this->fakeWebhookEndpoint(status: 500);

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
});

it('sets the response and error fields when the remote app responds with an error', function () {
    $this->fakeWebhookEndpoint(status: 500);

    baseWebhook()->dispatch();

    artisan('queue:work --once --sleep=0');

    Event::assertDispatched(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) {
        expect($event->response?->status())->toBe(500)
            ->and($event->errorType)->toBe(RequestException::class)
            ->and($event->errorMessage)->toContain('500');

        return true;
    });
});

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

it('does not overwrite a content type that was set explicitly', function () {
    $this->fakeWebhookEndpoint();

    WebhookCall::create()
        ->url('https://example.com/webhooks')
        ->withHeaders(['Content-Type' => 'application/xml'])
        ->sendRawBody('<xml>anotherOption</xml>')
        ->doNotSign()
        ->dispatch();

    artisan('queue:work --once --sleep=0');

    Http::assertSent(fn (Request $request) => $request->header('Content-Type') === ['application/xml']);
});

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
