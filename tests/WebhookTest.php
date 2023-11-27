<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use function PHPUnit\Framework\assertTrue;
use Spatie\WebhookServer\CallWebhookJob;
use Spatie\WebhookServer\Events\DispatchingWebhookCallEvent;
use Spatie\WebhookServer\Exceptions\CouldNotCallWebhook;
use Spatie\WebhookServer\Exceptions\InvalidBackoffStrategy;
use Spatie\WebhookServer\Exceptions\InvalidSigner;
use Spatie\WebhookServer\Exceptions\InvalidWebhookJob;

use Spatie\WebhookServer\WebhookCall;

beforeEach(function () {
    Queue::fake();
});

it('can dispatch a job that calls a webhook', function () {
    $url = 'https://localhost';

    WebhookCall::create()->url($url)->useSecret('123')->dispatch();

    Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) use ($url) {
        $config = config('webhook-server');

        expect($job->queue)->toEqual($config['queue'])
            ->and($job->webhookUrl)->toEqual($url)
            ->and($job->httpVerb)->toEqual($config['http_verb'])
            ->and($job->tries)->toEqual($config['tries'])
            ->and($job->requestTimeout)->toEqual($config['timeout_in_seconds'])
            ->and($job->backoffStrategyClass)->toEqual($config['backoff_strategy'])
            ->and(array_keys($job->headers))->toContain($config['signature_header_name'])
            ->and($job->verifySsl)->toEqual($config['verify_ssl'])
            ->and($job->throwExceptionOnFailure)->toEqual($config['throw_exception_on_failure'])
            ->and($job->tags)->toEqual($config['tags']);

        return true;
    });
});

it('can keep default config headers and set new ones', function () {
    $url = 'https://localhost';

    WebhookCall::create()->url($url)
        ->withHeaders(['User-Agent' => 'Spatie/Laravel-Webhook-Server'])
        ->useSecret('123')
        ->dispatch();

    Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) use ($url) {
        $config = config('webhook-server');

        expect($job->headers)->toHaveKeys(['Content-Type', 'User-Agent']);

        return true;
    });
});

it('can override default config headers', function () {
    $url = 'https://localhost';

    WebhookCall::create()->url($url)
        ->withHeaders(['Content-Type' => 'text/plain'])
        ->useSecret('123')
        ->dispatch();

    Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) use ($url) {
        $config = config('webhook-server');

        expect($job->headers)->toHaveKey('Content-Type');
        expect($job->headers['Content-Type'])->toEqual('text/plain');

        return true;
    });
});

it('can override default queue connection', function () {
    $url = 'https://localhost';

    WebhookCall::create()->url($url)
        ->onConnection('foo')
        ->useSecret('123')
        ->dispatch();

    Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job) use ($url) {
        $this->assertEquals('foo', $job->connection);

        return true;
    });
});

it('will throw an exception when calling a webhook without proving an url', function () {
    WebhookCall::create()->dispatch();
})->throws(CouldNotCallWebhook::class);

it('will throw an exception when no secret has been set', function () {
    WebhookCall::create()->url('https://localhost')->dispatch();
})->throws(CouldNotCallWebhook::class);

it('will not throw an exception if there is not secret and the request should not be signed', function () {
    WebhookCall::create()->doNotSign()->url('https://localhost')->dispatch();

    assertTrue(true);
});

it('will throw an exception when using an invalid backoff strategy', function () {
    WebhookCall::create()->useBackoffStrategy(static::class);
})->throws(InvalidBackoffStrategy::class);

it('will throw and exception when using an invalid signer', function () {
    WebhookCall::create()->signUsing(static::class);
})->throws(InvalidSigner::class);

it('will throw an exception when using an invalid webhook job', function () {
    $invalidJob = new class {
    };

    WebhookCall::create()->useJob($invalidJob::class);
})->throws(InvalidWebhookJob::class);

it('can get the UUID property', function () {
    $webhookCall = WebhookCall::create()->uuid('my-unique-identifier');

    expect($webhookCall->getUuid())
        ->toBeString()
        ->toEqual('my-unique-identifier');
});

it('can dispatch a job that calls a webhook if condition true', function () {
    $url = 'https://localhost';

    WebhookCall::create()->url($url)->useSecret('123')->dispatchIf(true);

    Queue::assertPushed(CallWebhookJob::class);
});

it('can not dispatch a job that calls a webhook if condition false', function () {
    $url = 'https://localhost';

    WebhookCall::create()->url($url)->useSecret('123')->dispatchIf(false);

    Queue::assertNotPushed(CallWebhookJob::class);
});

it('cannot dispatch a job that calls a webhook unless condition true', function () {
    $url = 'https://localhost';

    WebhookCall::create()->url($url)->useSecret('123')->dispatchUnless(true);

    Queue::assertNotPushed(CallWebhookJob::class);
});

it('can dispatch a job that calls a webhook unless condition false', function () {
    $url = 'https://localhost';

    WebhookCall::create()->url($url)->useSecret('123')->dispatchUnless(false);

    Queue::assertPushed(CallWebhookJob::class);
});

it('will fire an event right before the webhook is initially dispatched', function () {
    Event::fake();

    $url = 'https://localhost';

    WebhookCall::create()->url($url)->useSecret('123')->dispatchIf(true);

    Event::assertDispatched(DispatchingWebhookCallEvent::class, 1);
});
