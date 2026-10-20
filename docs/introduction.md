---
title: Introduction
weight: 1
---

This package sends webhooks from your Laravel application. It signs requests, retries failed deliveries through Laravel's queue, and fires events so you can track each attempt.

```php
use Spatie\WebhookServer\WebhookCall;

WebhookCall::create()
    ->url('https://other-app.com/webhooks')
    ->payload(['event' => 'order.created', 'order_id' => 123])
    ->useSecret('your-shared-secret')
    ->dispatch();
```

The receiving application gets a JSON POST request, a `Signature` header containing an HMAC-SHA256 signature, and a stable `Webhook-Id` header. Only a final `2xx` response succeeds. Failed deliveries are retried twice by default, after 10 and 100 seconds.

## What's new in v4

- Laravel's HTTP client sends requests, so you can use `Http::fake()` in application tests.
- JSON and raw bodies are signed using the exact bytes sent, unchanged across retries.
- Stable delivery IDs help receivers deduplicate requests.
- Optional response-aware retries support selected status codes, `Retry-After` and full jitter.
- Queued event listeners receive readable HTTP responses and transfer statistics, including binary data.

v4 requires PHP 8.4 and Laravel 13. Start with [installation and setup](/docs/laravel-webhook-server/v4/installation-and-setup), or read [the v3 to v4 upgrading guide](/docs/laravel-webhook-server/v4/upgrading).

If you need to receive webhooks, take a look at [laravel-webhook-client](https://github.com/spatie/laravel-webhook-client).
