---
title: Upgrading from v3
weight: 9
---

## Requirements and deployment

v4 requires PHP 8.4 and Laravel 13. Stay on v3 if you use older versions.

The usual `WebhookCall::create()->url(...)->payload(...)->useSecret(...)->dispatch()` API is unchanged, as are `dispatchSync()`, `doNotSign()` and custom backoff strategies. Standard JSON payloads produce the same default HMAC-SHA256 signatures, so receivers using the default signature do not need changes.

Drain pending webhook and listener jobs using the old version before deploying v4, then restart queue workers on the new code. Do not run old and new workers against the same pending jobs.

Laravel merges new configuration defaults into published configuration. You do not need to overwrite your file. Add the new keys only if you want to customize them. The new retry options are disabled by default; the new `Webhook-Id` header is enabled by default and can be disabled.

## Custom signers receive the serialized body

The second argument changed from `array $payload` to `string $body`:

```php
public function calculateSignature(string $webhookUrl, string $body, string $secret): string
{
    return hash_hmac('sha256', $body, $secret);
}
```

Remove JSON encoding from your signer: the body is already encoded. If your algorithm needs structured data, decode it explicitly and decide how it handles raw bodies. Update direct calls to `DefaultSigner::calculateSignature()` too. Raw bodies can now be signed without changing their bytes.

The body is prepared once before dispatch and reused on every attempt. A custom `generateBody(): string` override now runs before the job reaches the queue; it must not depend on worker-only state or the attempt number. Its output is both signed and sent. Event payloads remain arrays for JSON calls and strings for raw calls.

GET webhooks still send query parameters and sign the payload's JSON representation. Unsigned GET query parameters are not JSON-encoded. Middleware that changes a signed body must also update its signature.

## Response and exception types

`CallWebhookJob::getResponse()` and result events' `$response` property now hold an `Illuminate\Http\Client\Response` rather than a `GuzzleHttp\Psr7\Response`. Update PSR-7 type hints and `instanceof` checks. Laravel's response forwards methods such as `getStatusCode()` and `getBody()`, but prefer `status()` and `body()`.

HTTP `4xx`/`5xx` failures and connection failures report Laravel's `RequestException` and `ConnectionException` through `errorType` and `errorMessage`. Other exceptions that fail an attempt also populate those fields; v3 left them empty.

`CouldNotCallWebhook`, `InvalidBackoffStrategy`, `InvalidSigner` and `InvalidWebhookJob` now extend `LogicException`. Catching their own class names or `Exception` still works.

## Custom HTTP clients and job subclasses

Binding `GuzzleHttp\Client` in the container no longer swaps the transport. Use Laravel's HTTP global middleware instead.

`getClient(): ClientInterface` was replaced by `createRequest(): PendingRequest`. The previous `createRequest(array $body): Response` method no longer exists. Override `requestOptions(): array` to customize Guzzle options independently.

Overrides of `handle()` and `failed(Throwable $exception)` must declare `void` return types. Resolve `DefaultSigner` through the container if you rely on its configured signature header name, which is now injected into its constructor.

## Invalid payloads fail before dispatch

JSON encoding uses `JSON_THROW_ON_ERROR`. Invalid UTF-8, recursive arrays and other unencodable values throw `JsonException` before dispatch for JSON bodies and signed GET payloads. Handle the exception at the dispatch call, not in a failed-delivery listener. Unsigned JSON bodies are validated too; unsigned GET query parameters do not require JSON encoding.

Raw bodies are not JSON-encoded. Custom jobs setting `outputType` to `RAW` must supply a string; an array throws `UnexpectedValueException` before dispatch.

## Delivery IDs and retry options

The default `Webhook-Id` header contains the event UUID and remains stable across attempts. Rename or disable it globally with `webhook_id_header_name`, or per call with `withWebhookIdHeader()`. Explicit caller headers take precedence regardless of casing. Neither this header nor the optional timestamp is authenticated by the default signer.

`retryOn()`, `respectRetryAfter()` and `withJitter()` are opt-in. Their configuration keys are `retryable_status_codes`, `respect_retry_after`, and `retry_with_jitter`. Excluded HTTP statuses are immediately final; connection failures still retry. `Retry-After` is a minimum after jitter. Ensure delays fit your queue driver's limits.

## Queued listeners and stream snapshots

Queued listeners now receive reconstructed Laravel responses and Guzzle statistics with readable bodies and preserved HTTP metadata. Synchronous listeners keep their original objects, and seekable streams retain their positions.

Jobs and dispatching/result events preserve binary bodies, headers, metadata and error messages using base64 encoding. Queue messages are larger and can hold sensitive data; encoding is not encryption. Check queue size limits and access controls. Custom metadata, cookie jars and handler data must still be serializable. Custom response subclasses and their extra runtime state are not preserved.

Non-seekable streams are read once and cached for shared snapshots and repeated serialization. The first snapshot consumes the original stream. Buffer or replace it before dispatching events when synchronous listeners also need to read it. Previously consumed bytes cannot be recovered.

## HTTP behavior and application-wide configuration

Only final `2xx` responses succeed. Redirects still follow Guzzle's default policy, and final `3xx` responses fail and retry by default. Such failures report `Exception` with the message `Webhook call failed`.

Raw bodies, configured Content-Type headers including array values, GET query parameters, and Guzzle's cookie, connection-timeout and TLS defaults are preserved. Laravel's implicit cookie jar, ten-second connection timeout and TLS override are not applied. Total timeout, SSL verification, proxy, certificate, private key and transfer statistics still pass through.

Laravel HTTP fakes, global options and global middleware now apply. Explicit package transport settings override corresponding global options; global header values can be combined with webhook headers rather than replaced. Review application-wide HTTP configuration when upgrading.
