---
title: Retrying failed webhooks
weight: 5
---

A final response outside `2xx`, a connection failure, or a request exceeding the timeout fails the attempt. Redirects follow Guzzle's default redirect policy; a final `3xx` response fails too.

By default, the package makes three attempts in total. It waits 10 seconds before the second and 100 seconds before the third. The default exponential backoff then grows to 1,000 seconds and beyond when you configure more attempts, capped at 100,000 seconds.

Set `tries` and `timeout_in_seconds` in the config file, or override them using `maximumTries(5)` and `timeoutInSeconds(5)`.

## Response-aware retries

The default policy retries every unsuccessful HTTP status. Limit retries, honor the receiver's minimum delay, and add full jitter with:

```php
WebhookCall::create()
    ->url('https://other-app.com/webhooks')
    ->payload(['key' => 'value'])
    ->useSecret('your-shared-secret')
    ->retryOn([408, 425, 429, 500, 502, 503, 504])
    ->respectRetryAfter()
    ->withJitter()
    ->dispatch();
```

- `retryOn(null)` restores retrying all unsuccessful statuses.
- `retryOn([])` disables HTTP-status retries. Connection failures still retry.
- An excluded status immediately fires both `WebhookCallFailedEvent` and `FinalWebhookCallFailedEvent`, and honors `throwExceptionOnFailure()`.
- `respectRetryAfter()` accepts non-negative seconds or an HTTP date. Invalid values are ignored; past dates add no delay. The header never shortens the backoff.
- `withJitter()` chooses a random delay between zero and the backoff strategy's delay, inclusive. `Retry-After` is applied afterwards as a minimum.

Disable the last two options per call by passing `false`. The global config keys are `retryable_status_codes`, `respect_retry_after`, and `retry_with_jitter`. All three options are opt-in.

## Custom backoff strategies

Implement `Spatie\WebhookServer\BackoffStrategy\BackoffStrategy` with a `waitInSecondsAfterAttempt(int $attempt): int` method returning a delay in seconds. Set the `backoff_strategy` config key to that class, or use `useBackoffStrategy(YourBackoffStrategy::class)` per call.

## Queue limits and final failures

Retries use delayed queue jobs, not immediate HTTP-client retries. Synchronous calls do not wait or loop. Make sure the resulting delay, including `Retry-After`, fits your queue driver's limits. Amazon SQS permits at most 15 minutes.

Listen to [webhook events](/docs/laravel-webhook-server/v4/events) to record failures. To mark the queue job as failed and rethrow the exception on the final failure, call `throwExceptionOnFailure()` or enable `throw_exception_on_failure` globally.
