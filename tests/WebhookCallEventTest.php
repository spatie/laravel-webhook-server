<?php

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

use Spatie\TestTime\TestTime;
use Spatie\WebhookServer\Events\DispatchingWebhookCallEvent;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
use Spatie\WebhookServer\Tests\TestClasses\BinaryBodyWebhookJob;
use Spatie\WebhookServer\Tests\TestClasses\QueuedWebhookListener;
use Spatie\WebhookServer\WebhookCall;

afterEach(function () {
    RequestException::truncate();
    TestTime::unfreeze();
});

it('delivers HTTP results to a real queued listener', function (int $status, string $body) {
    RequestException::dontTruncate();
    QueuedWebhookListener::$events = [];
    Event::listen([WebhookCallSucceededEvent::class, FinalWebhookCallFailedEvent::class], QueuedWebhookListener::class);
    Http::fake(['*' => Http::response($body, $status, ['X-Result' => ['first', "caf\xe9"]])]);

    WebhookCall::create()->url('https://example.com/webhooks')
        ->payload(['document' => 'queued'])->useSecret('secret')->maximumTries(1)->dispatch();

    artisan('queue:work --once --sleep=0');
    artisan('queue:work --once --sleep=0');

    expect(QueuedWebhookListener::$events)->toHaveCount(1);

    $event = QueuedWebhookListener::$events[0];

    expect($event)->toBeInstanceOf($status === 201 ? WebhookCallSucceededEvent::class : FinalWebhookCallFailedEvent::class)
        ->and($event->response)->toBeInstanceOf(Response::class)
        ->and($event->response->status())->toBe($status)
        ->and($event->response->body())->toBe($body)
        ->and($event->response->headers()['X-Result'])->toBe(['first', "caf\xe9"])
        ->and($event->response->cookies)->toBeFalse()
        ->and($event->transferStats?->getRequest()->getMethod())->toBe('POST')
        ->and((string) $event->transferStats?->getRequest()->getBody())->toBe('{"document":"queued"}')
        ->and((string) $event->transferStats?->getEffectiveUri())->toBe('https://example.com/webhooks');

    if ($status === 503) {
        expect($event->errorMessage)->toContain($body);
    }
})->with([201, 503])->with(['{"stored":true}', "binary\xff\0body"]);

it('preserves binary requests and signatures through queued listeners and retries', function (bool $raw, string $body) {
    TestTime::freeze();
    QueuedWebhookListener::$events = [];
    Event::listen([
        DispatchingWebhookCallEvent::class,
        WebhookCallFailedEvent::class,
        WebhookCallSucceededEvent::class,
    ], QueuedWebhookListener::class);
    Http::fakeSequence()->pushStatus(503)->pushStatus(201);

    if (! $raw) {
        config()->set('webhook-server.webhook_job', BinaryBodyWebhookJob::class);
    }

    $webhook = WebhookCall::create()->url('https://example.com/webhooks')->useSecret('secret')
        ->payload(['document' => 'queued'])->withHeaders(['X-Bytes' => ['first', "caf\xe9"]])
        ->meta(['context' => "\xff"])->maximumTries(2);

    if ($raw) {
        $webhook->sendRawBody($body);
    }

    $webhook->dispatch();

    artisan('queue:work --once --sleep=0');
    artisan('queue:work --once --sleep=0');
    artisan('queue:work --once --sleep=0');
    TestTime::addSeconds(10);
    artisan('queue:work --once --sleep=0');
    artisan('queue:work --once --sleep=0');

    Http::assertSentCount(2);

    foreach (Http::recorded() as [$request]) {
        expect($request->body())->toBe($body)
            ->and($request->header('Signature'))->toBe([hash_hmac('sha256', $body, 'secret')]);
    }

    expect(QueuedWebhookListener::$events)->toHaveCount(3);

    foreach (QueuedWebhookListener::$events as $index => $event) {
        expect($event)->toBeInstanceOf([
            DispatchingWebhookCallEvent::class,
            WebhookCallFailedEvent::class,
            WebhookCallSucceededEvent::class,
        ][$index])->and($event->payload)->toBe($raw ? $body : ['document' => 'queued'])
            ->and($event->headers['X-Bytes'])->toBe(['first', "caf\xe9"])
            ->and($event->meta)->toBe(['context' => "\xff"]);

        if ($event instanceof DispatchingWebhookCallEvent) {
            continue;
        }

        expect((string) $event->transferStats?->getRequest()->getBody())->toBe($body);
    }
})->with([
    'raw' => [true, "raw\xff\0body"],
    'custom body' => [false, "generated\xfe\0body"],
]);

it('delivers connection failures to a real queued listener', function () {
    QueuedWebhookListener::$events = [];
    Event::listen(FinalWebhookCallFailedEvent::class, QueuedWebhookListener::class);
    Http::fake(['*' => Http::failedConnection()]);

    WebhookCall::create()->url('https://example.com/webhooks')
        ->useSecret('secret')->maximumTries(1)->dispatch();

    artisan('queue:work --once --sleep=0');
    artisan('queue:work --once --sleep=0');

    expect(QueuedWebhookListener::$events)->toHaveCount(1)
        ->and(QueuedWebhookListener::$events[0]->response)->toBeNull()
        ->and(QueuedWebhookListener::$events[0]->errorType)->toBe(ConnectionException::class);
});

it('preserves HTTP metadata and leaves the original streams readable when serializing events', function () {
    $request = (new PsrRequest('PUT', 'https://example.com:8443/path?key=value', ['X-Request' => ['one', "tw\xf4"]], "request\xffbody", '2'))
        ->withRequestTarget('/custom-target?key=value');
    $psrResponse = new PsrResponse(202, ['X-Response' => ['three', "fo\xf9r"]], "response\xff\0body", '1.0', "Stored caf\xe9");
    $stats = new TransferStats($request, $psrResponse, 1.25, ["error\xff"], [
        'total_time' => 1.25,
        'redirect_count' => 2,
        'content_type' => "text/plain; note=\"caf\xe9\"",
    ]);
    $response = new Response($psrResponse);
    $response->cookies = CookieJar::fromArray(['session' => 'known'], 'example.com');
    $response->transferStats = $stats;
    $request->getBody()->seek(3);
    $psrResponse->getBody()->seek(4);

    $event = new WebhookCallSucceededEvent('PUT', 'https://example.com:8443/path?key=value', ['entry' => 42], [], ['tracking' => 'value'], ['webhooks'], 2, $response, null, null, 'delivery-123', $stats);
    $restored = unserialize(json_decode(json_encode(serialize($event), JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR));

    expect($request->getBody()->tell())->toBe(3)
        ->and($psrResponse->getBody()->tell())->toBe(4)
        ->and($restored->payload)->toBe(['entry' => 42])
        ->and($restored->meta)->toBe(['tracking' => 'value'])
        ->and($restored->tags)->toBe(['webhooks'])
        ->and($restored->uuid)->toBe('delivery-123')
        ->and($restored->attempt)->toBe(2)
        ->and($restored->response->status())->toBe(202)
        ->and($restored->response->body())->toBe("response\xff\0body")
        ->and($restored->response->headers()['X-Response'])->toBe(['three', "fo\xf9r"])
        ->and($restored->response->toPsrResponse()->getProtocolVersion())->toBe('1.0')
        ->and($restored->response->reason())->toBe("Stored caf\xe9")
        ->and($restored->response->cookies()->getCookieByName('session')->getValue())->toBe('known')
        ->and($restored->response->handlerStats())->toBe([
            'total_time' => 1.25,
            'redirect_count' => 2,
            'content_type' => "text/plain; note=\"caf\xe9\"",
        ])
        ->and($restored->transferStats->getTransferTime())->toBe(1.25)
        ->and($restored->transferStats->getHandlerErrorData())->toBe(["error\xff"])
        ->and($restored->transferStats->getHandlerStats())->toBe([
            'total_time' => 1.25,
            'redirect_count' => 2,
            'content_type' => "text/plain; note=\"caf\xe9\"",
        ])
        ->and($restored->transferStats->getResponse()->getReasonPhrase())->toBe("Stored caf\xe9")
        ->and((string) $restored->transferStats->getRequest()->getUri())->toBe('https://example.com:8443/path?key=value')
        ->and($restored->transferStats->getRequest()->getRequestTarget())->toBe('/custom-target?key=value')
        ->and($restored->transferStats->getRequest()->getProtocolVersion())->toBe('2')
        ->and($restored->transferStats->getRequest()->getHeader('X-Request'))->toBe(['one', "tw\xf4"])
        ->and((string) $restored->transferStats->getRequest()->getBody())->toBe("request\xffbody");
});

it('retains shared non-seekable bodies across statistics and repeated event serialization', function () {
    $request = new PsrRequest('POST', 'https://example.com', [], new NoSeekStream(Utils::streamFor("request\xff")));
    $psrResponse = new PsrResponse(200, [], new NoSeekStream(Utils::streamFor("response\xfe")));
    $stats = new TransferStats($request, $psrResponse, 0.1);
    $response = new Response($psrResponse);
    $response->transferStats = $stats;
    $event = new WebhookCallSucceededEvent('POST', 'https://example.com', [], [], [], [], 1, $response, null, null, 'delivery', $stats);

    foreach ([$event, $event, clone $event] as $listenerEvent) {
        $restored = unserialize(json_decode(json_encode(serialize($listenerEvent), JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR));

        expect($restored->response->body())->toBe("response\xfe")
            ->and((string) $restored->response->transferStats->getResponse()->getBody())->toBe("response\xfe")
            ->and((string) $restored->transferStats->getResponse()->getBody())->toBe("response\xfe")
            ->and((string) $restored->response->transferStats->getRequest()->getBody())->toBe("request\xff")
            ->and((string) $restored->transferStats->getRequest()->getBody())->toBe("request\xff");
    }
});
