---
title: Testing webhooks
weight: 8
---

## Assert HTTP requests

Webhooks use Laravel's HTTP client. Fake the endpoint and dispatch synchronously to inspect a request without making a network call:

```php
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\WebhookServer\WebhookCall;

it('sends the order webhook', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://other-app.com/webhooks' => Http::response('', 204),
    ]);

    WebhookCall::create()
        ->url('https://other-app.com/webhooks')
        ->payload(['order_id' => 123])
        ->useSecret('your-shared-secret')
        ->dispatchSync();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://other-app.com/webhooks'
        && $request->method() === 'POST'
        && $request->body() === '{"order_id":123}'
        && $request->hasHeader('Signature', hash_hmac('sha256', '{"order_id":123}', 'your-shared-secret')));
});
```

For queued dispatch, run the job or queue worker in the same application process before asserting on the HTTP request. HTTP fakes are not shared with a separate worker process.

## Assert dispatch without executing the job

Use `Bus::fake()` or `Queue::fake()` when you only need to assert that your application scheduled a webhook. This avoids sending requests:

```php
use Illuminate\Support\Facades\Queue;
use Spatie\WebhookServer\CallWebhookJob;
use Spatie\WebhookServer\WebhookCall;

Queue::fake();

WebhookCall::create()
    ->url('https://other-app.com/webhooks')
    ->payload(['order_id' => 123])
    ->useSecret('your-shared-secret')
    ->dispatch();

Queue::assertPushed(CallWebhookJob::class);
```

## Contributing to the package

Run `composer test`, `composer analyse`, and `composer format-test`. Static analysis uses Larastan and PHPStan level 10 without a baseline. Use `composer format` to apply the formatting rules.
