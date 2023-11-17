<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\TransferStats;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use function Pest\Laravel\artisan;
use function Pest\Laravel\mock;
use Spatie\TestTime\TestTime;
use Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;

use Spatie\WebhookServer\Tests\TestClasses\TestClient;
use Spatie\WebhookServer\WebhookCall;

function baseWebhook(): WebhookCall
{
    return WebhookCall::create()
        ->url('https://example.com/webhooks')
        ->useSecret('abc')
        ->payload(['a' => 1]);
}

function baseRequest(array $overrides = []): array
{
    $defaultProperties = [
        'method' => 'post',
        'url' => 'https://example.com/webhooks',
        'options' => [
            'timeout' => 3,
            'body' => json_encode(['a' => 1]),
            'verify' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Signature' => '1f14a62b15ba5095326d6c75c3e2e6b462dd71e1c4b7fbdac0f32309adb7be5f',
            ],
            'on_stats' => function (TransferStats $stats) {
            },
        ],
    ];

    return array_merge($defaultProperties, $overrides);
}

function baseGetRequest(array $overrides = []): array
{
    $defaultProperties = [
        'method' => 'get',
        'url' => 'https://example.com/webhooks',
        'options' => [
            'timeout' => 3,
            'query' => ['a' => 1],
            'verify' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Signature' => '1f14a62b15ba5095326d6c75c3e2e6b462dd71e1c4b7fbdac0f32309adb7be5f',
            ],
            'on_stats' => function (TransferStats $stats) {
            },
        ],
    ];

    return array_merge($defaultProperties, $overrides);
}

beforeEach(function () {
    Event::fake();

    $this->testClient = new TestClient();

    app()->bind(Client::class, function () {
        return $this->testClient;
    });
});

it('can make a webhook call', function () {
    baseWebhook()->dispatch();

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([baseRequest()]);
});

it('can make a legacy synchronous webhook call', function () {
    baseWebhook()->dispatchSync();

    $this
        ->testClient
        ->assertRequestsMade([baseRequest()]);
});

it('can make a synchronous webhook call', function () {
    baseWebhook()->dispatchSync();

    $this
        ->testClient
        ->assertRequestsMade([baseRequest()]);
});

it('can use a different HTTP verb', function () {
    baseWebhook()
        ->useHttpVerb('put')
        ->dispatch();

    $baseResponse = baseRequest(['method' => 'put']);

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseResponse]);
});

it('uses query option when http verb is get', function () {
    baseWebhook()
        ->useHttpVerb('get')
        ->dispatch();

    $baseResponse = baseGetRequest();

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseResponse]);
});

it('can add extra headers', function () {
    $extraHeaders = [
        'Content-Type' => 'application/json',
        'header1' => 'value1',
        'headers2' => 'value2',
    ];

    baseWebhook()
        ->withHeaders($extraHeaders)
        ->dispatch();

    $baseRequest = baseRequest();

    $baseRequest['options']['headers'] = array_merge(
        $baseRequest['options']['headers'],
        $extraHeaders
    );

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseRequest]);
});

it('will not set a signature header when the request should not be signed', function () {
    baseWebhook()
        ->doNotSign()
        ->dispatch();

    $baseRequest = baseRequest();

    unset($baseRequest['options']['headers']['Signature']);

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseRequest]);
});

it('can disable verifying SSL', function () {
    baseWebhook()->doNotVerifySsl()->dispatch();

    $baseRequest = baseRequest();
    $baseRequest['options']['verify'] = false;

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseRequest]);
});

it('will use mutual TLS without passphrases', function () {
    baseWebhook()
        ->mutualTls('foobar', 'barfoo')
        ->dispatch();

    $baseRequest = baseRequest();

    $baseRequest['options']['cert'] = ['foobar', null];
    $baseRequest['options']['ssl_key'] = ['barfoo', null];

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseRequest]);
});

it('will use mutual TLS with passphrases', function () {
    baseWebhook()
        ->mutualTls('foobar', 'barfoo', 'foobarpassword', 'barfoopassword')
        ->dispatch();

    $baseRequest = baseRequest();

    $baseRequest['options']['cert'] = ['foobar', 'foobarpassword'];
    $baseRequest['options']['ssl_key'] = ['barfoo', 'barfoopassword'];

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseRequest]);
});

it('will use mutual TLS with certificate authority', function () {
    baseWebhook()
        ->mutualTls('foobar', 'barfoo')
        ->verifySsl('foofoo')
        ->dispatch();

    $baseRequest = baseRequest();

    $baseRequest['options']['cert'] = ['foobar', null];
    $baseRequest['options']['ssl_key'] = ['barfoo', null];
    $baseRequest['options']['verify'] = 'foofoo';

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseRequest]);
});

it('will use a proxy', function () {
    baseWebhook()
        ->useProxy('https://proxy.test')
        ->dispatch();

    $baseRequest = baseRequest();

    $baseRequest['options']['proxy'] = 'https://proxy.test';

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseRequest]);
});

it('will use a proxy array', function () {
    baseWebhook()
        ->useProxy([
            'http' => 'http://proxy.test',
            'https' => 'https://proxy.test',
        ])
        ->dispatch();

    $baseRequest = baseRequest();

    $baseRequest['options']['proxy'] = [
        'http' => 'http://proxy.test',
        'https' => 'https://proxy.test',
    ];

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseRequest]);
});

test('by default it will retry 3 times with the exponential backoff strategy', function () {
    $this->testClient->letEveryRequestFail();

    baseWebhook()->dispatch();

    mock(ExponentialBackoffStrategy::class, function (MockInterface $mock) {
        $mock->shouldReceive('waitInSecondsAfterAttempt')->withArgs([1])->once()->andReturns(10);
        $mock->shouldReceive('waitInSecondsAfterAttempt')->withArgs([2])->once()->andReturns(100);
        $mock->shouldReceive('waitInSecondsAfterAttempt')->withArgs([3])->never();

        return $mock;
    });

    artisan('queue:work --once');
    Event::assertDispatched(WebhookCallFailedEvent::class, 1);

    TestTime::addSeconds(9);
    artisan('queue:work --once');
    Event::assertDispatched(WebhookCallFailedEvent::class, 1);

    TestTime::addSeconds(1);
    artisan('queue:work --once');
    Event::assertDispatched(WebhookCallFailedEvent::class, 2);

    TestTime::addSeconds(100);
    artisan('queue:work --once');
    Event::assertDispatched(WebhookCallFailedEvent::class, 3);
    Event::assertDispatched(FinalWebhookCallFailedEvent::class, 1);
    $this->testClient->assertRequestCount(3);

    TestTime::addSeconds(1000);
    artisan('queue:work --once');
    Event::assertDispatched(WebhookCallFailedEvent::class, 3);
    Event::assertDispatched(FinalWebhookCallFailedEvent::class, 1);
    $this->testClient->assertRequestCount(3);
});

it('sets the response field on request failure', function () {
    $this->testClient->throwRequestException();

    baseWebhook()->dispatch();

    artisan('queue:work --once');
    Event::assertDispatched(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) {
        $this->assertNotNull($event->response);

        return true;
    });
});

it('sets the error fields on connection failure', function () {
    $this->testClient->throwConnectionException();

    baseWebhook()->dispatch();

    artisan('queue:work --once');

    Event::assertDispatched(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) {
        expect($event->errorType)->not->toBeNull()
            ->and($event->errorMessage)->not->toBeNull();

        return true;
    });
});

it('generate job failed event if an exception throws and throw exception on failure config is set', function () {
    $this->testClient->throwConnectionException();

    baseWebhook()->maximumTries(1)->throwExceptionOnFailure()->dispatch();

    artisan('queue:work --once');

    Event::assertDispatched(JobFailed::class, function (JobFailed $event) {
        expect($event->exception)->toBeInstanceOf(ConnectException::class);

        return true;
    });
});

it('send raw body data if rawBody is set', function () {
    $testBody = "<xml>anotherOption</xml>";
    WebhookCall::create()
        ->url('https://example.com/webhooks')
        ->useSecret('abc')
        ->sendRawBody($testBody)
        ->doNotSign()
        ->dispatch();

    $baseRequest = baseRequest();

    $baseRequest['options']['body'] = $testBody;
    unset($baseRequest['options']['headers']['Signature']);

    artisan('queue:work --once');

    $this
        ->testClient
        ->assertRequestsMade([$baseRequest]);
});


it('send raw body data in event if rawBody is set', function () {
    $this->testClient->throwConnectionException();

    $testBody = "<xml>anotherOption</xml>";
    WebhookCall::create()
        ->url('https://example.com/webhooks')
        ->useSecret('abc')
        ->sendRawBody($testBody)
        ->doNotSign()
        ->dispatch();

    $baseRequest = baseRequest();

    $baseRequest['options']['body'] = $testBody;
    unset($baseRequest['options']['headers']['Signature']);

    artisan('queue:work --once');

    Event::assertDispatched(WebhookCallFailedEvent::class, function (WebhookCallFailedEvent $event) use ($testBody) {
        expect($event->errorType)->not->toBeNull()
            ->and($event->errorMessage)->not->toBeNull()
            ->and($event->payload)->toBe($testBody);

        return true;
    });
});
