<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

use Random\Engine\Mt19937;
use Random\Randomizer;
use Spatie\TestTime\TestTime;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
use Spatie\WebhookServer\WebhookCall;

beforeEach(function () {
    Event::fake();
    TestTime::freeze(now()->setDate(2026, 10, 19)->setTime(12, 0));
});

afterEach(fn () => TestTime::unfreeze());

it('only retries the selected unsuccessful HTTP statuses', function (int $status, bool $retry) {
    $this->fakeWebhookEndpoint($status);

    WebhookCall::create()->url('https://example.com/webhooks')->useSecret('secret')
        ->retryOn([429, 503])->maximumTries(2)->dispatch();

    artisan('queue:work --once --sleep=0');

    Event::assertDispatched(FinalWebhookCallFailedEvent::class, $retry ? 0 : 1);

    TestTime::addSeconds(10);
    artisan('queue:work --once --sleep=0');

    Http::assertSentCount($retry ? 2 : 1);
    Event::assertDispatched(WebhookCallFailedEvent::class, $retry ? 2 : 1);
    Event::assertDispatched(FinalWebhookCallFailedEvent::class, 1);
    Event::assertNotDispatched(WebhookCallSucceededEvent::class);
})->with([[422, false], [429, true], [503, true], [302, false]]);

it('continues retrying connection failures when no HTTP statuses are retryable', function () {
    Http::fake(['*' => Http::failedConnection()]);

    WebhookCall::create()->url('https://example.com/webhooks')->useSecret('secret')
        ->retryOn([])->maximumTries(2)->dispatch();

    artisan('queue:work --once --sleep=0');
    Event::assertDispatched(WebhookCallFailedEvent::class, 1);
    Event::assertNotDispatched(FinalWebhookCallFailedEvent::class);

    TestTime::addSeconds(10);
    artisan('queue:work --once --sleep=0');

    Event::assertDispatched(WebhookCallFailedEvent::class, 2);
    Event::assertDispatched(FinalWebhookCallFailedEvent::class, 1);
});

it('honors Retry-After as a minimum and ignores malformed values', function (string $retryAfter, int $delay) {
    Http::fakeSequence()->push('', 503, ['Retry-After' => $retryAfter])->pushStatus(200);

    WebhookCall::create()->url('https://example.com/webhooks')->useSecret('secret')
        ->respectRetryAfter()->dispatch();

    artisan('queue:work --once --sleep=0');
    TestTime::addSeconds($delay - 1);
    artisan('queue:work --once --sleep=0');
    Http::assertSentCount(1);

    TestTime::addSeconds(1);
    artisan('queue:work --once --sleep=0');
    Http::assertSentCount(2);
    Event::assertDispatched(WebhookCallSucceededEvent::class);
})->with([
    'seconds' => ['120', 120],
    'leading zeros' => ['00120', 120],
    'HTTP date' => ['Mon, 19 Oct 2026 12:02:00 GMT', 120],
    'RFC 850 date' => ['Monday, 19-Oct-26 12:02:00 GMT', 120],
    'RFC 850 fifty-year boundary' => ['Monday, 19-Oct-76 12:00:00 GMT', 1577923200],
    'RFC 850 beyond fifty years' => ['Monday, 19-Oct-76 12:00:01 GMT', 10],
    'asctime date' => ['Mon Oct 19 12:02:00 2026', 120],
    'asctime single-digit day' => ['Mon Nov  2 12:00:00 2026', 1209600],
    'shorter than backoff' => ['2', 10],
    'zero' => ['0', 10],
    'past date' => ['Mon, 19 Oct 2026 11:58:00 GMT', 10],
    'relative date' => ['next Monday', 10],
    'negative seconds' => ['-12', 10],
    'overflow' => ['999999999999999999999999999999', 10],
    'invalid date' => ['Mon, 32 Oct 2026 12:02:00 GMT', 10],
]);

it('ignores Retry-After by default', function () {
    Http::fakeSequence()->push('', 429, ['Retry-After' => '120'])->pushStatus(200);

    WebhookCall::create()->url('https://example.com/webhooks')->useSecret('secret')->dispatch();

    artisan('queue:work --once --sleep=0');
    TestTime::addSeconds(10);
    artisan('queue:work --once --sleep=0');

    Http::assertSentCount(2);
    Event::assertDispatched(WebhookCallSucceededEvent::class);
});

it('applies full jitter without shortening Retry-After', function (bool $respectRetryAfter, string $retryAfter, int $delay) {
    app()->instance(Randomizer::class, new Randomizer(new Mt19937(123)));
    Http::fakeSequence()->push('', 503, ['Retry-After' => $retryAfter])->pushStatus(200);

    WebhookCall::create()->url('https://example.com/webhooks')->useSecret('secret')
        ->withJitter()->respectRetryAfter($respectRetryAfter)->dispatch();

    artisan('queue:work --once --sleep=0');
    TestTime::addSeconds($delay - 1);
    artisan('queue:work --once --sleep=0');
    Http::assertSentCount(1);

    TestTime::addSeconds(1);
    artisan('queue:work --once --sleep=0');
    Http::assertSentCount(2);
    Event::assertDispatched(WebhookCallSucceededEvent::class);
})->with([
    'jitter without Retry-After' => [false, '120', 3],
    'long Retry-After' => [true, '15', 15],
    'short Retry-After' => [true, '2', 3],
]);

it('uses configured retry settings and allows per-call overrides', function (int $status, bool $override, ?int $delay) {
    config()->set('webhook-server.retryable_status_codes', [503]);
    config()->set('webhook-server.respect_retry_after', true);
    config()->set('webhook-server.retry_with_jitter', true);
    app()->instance(Randomizer::class, new Randomizer(new Mt19937(123)));
    Http::fakeSequence()->push('', $status, ['Retry-After' => '7'])->pushStatus(200);

    $webhook = WebhookCall::create()->url('https://example.com/webhooks')->useSecret('secret');

    if ($override) {
        $webhook->retryOn([422])->respectRetryAfter(false)->withJitter(false);
    }

    $webhook->dispatch();

    artisan('queue:work --once --sleep=0');

    if ($delay === null) {
        Event::assertDispatched(FinalWebhookCallFailedEvent::class, 1);

        TestTime::addSeconds(100);
        artisan('queue:work --once --sleep=0');
        Http::assertSentCount(1);

        return;
    }

    TestTime::addSeconds($delay - 1);
    artisan('queue:work --once --sleep=0');
    Http::assertSentCount(1);

    TestTime::addSeconds(1);
    artisan('queue:work --once --sleep=0');
    Http::assertSentCount(2);
    Event::assertDispatched(WebhookCallSucceededEvent::class);
})->with([
    'configured status filter' => [422, false, null],
    'configured delay' => [503, false, 7],
    'per-call overrides' => [422, true, 10],
]);
