# Upgrading

## From v3 to v4

Webhooks are now sent with Laravel's HTTP client instead of a Guzzle client that the package built itself.

### Requirements

v4 requires PHP 8.4 and Laravel 13. Stay on v3 if you are on an older version.

### Most applications need little or no calling-code changes

`WebhookCall::create()->url(...)->payload(...)->useSecret(...)->dispatch()` is unchanged, as are `dispatchSync()`, `doNotSign()` and custom backoff strategies. Standard JSON payloads produce the same default HMAC-SHA256 signatures, so receivers using the default signature do not need to change.

Review custom signers, response type hints and `CallWebhookJob` subclasses using the sections below. New retry options are disabled by default; the existing retry policy and delays remain the defaults. A `Webhook-Id` header is now sent by default and can be disabled.

Laravel merges the new config defaults into existing published configuration. You do not need to overwrite your config file; add the new keys only if you want to customize them.

Drain pending webhook and listener jobs with the old version before deploying v4, then restart queue workers on the new code. Do not run old and new worker code against the same pending jobs during the upgrade.

### Custom signers now receive the serialized body

The second argument to `Signer::calculateSignature()` changed from `array $payload` to `string $body`:

```php
public function calculateSignature(string $webhookUrl, string $body, string $secret): string
{
    return hash_hmac('sha256', $body, $secret);
}
```

Remove any JSON encoding from your signer: the body is already encoded. If your algorithm needs structured payload data, decode the body explicitly, and consider how the signer should handle raw bodies. Update direct calls to `DefaultSigner::calculateSignature()` too. The default signer now supports signing raw bodies, including XML and pre-encoded JSON, without altering their bytes.

The package prepares each body once before dispatch and sends those bytes on every attempt. `generateBody(): string` overrides now run during dispatch, before the job reaches the queue; they must not depend on worker-only state or the current attempt. Their output is both signed and sent. Event payloads remain arrays for JSON calls and strings for raw calls.

GET webhooks retain their existing behavior: the payload goes into query parameters and its JSON representation is signed. Unsigned GET query parameters are not JSON-encoded. Middleware that changes a body after signing must also update its signature.

### The response is now an `Illuminate\Http\Client\Response`

`CallWebhookJob::getResponse()` and the `$response` property on `WebhookCallSucceededEvent`, `WebhookCallFailedEvent` and `FinalWebhookCallFailedEvent` now hold an `Illuminate\Http\Client\Response` instead of a `GuzzleHttp\Psr7\Response`.

That class is not a PSR-7 response, so type hints and `instanceof` checks against `Psr\Http\Message\ResponseInterface` no longer match. It does forward unknown method calls to the underlying PSR-7 response, so calls like `getStatusCode()` and `getBody()` keep working.

```php
// v3
$event->response->getStatusCode();

// v4
$event->response->status();
```

### Guzzle can no longer be swapped through the container

v3 resolved `GuzzleHttp\Client` from the container, so you could bind your own client to add middleware. That binding is gone. Register a global middleware on Laravel's HTTP client instead.

```php
Http::globalRequestMiddleware(...);
```

### `getClient()` and `createRequest()` changed

If you extend `CallWebhookJob`, note that `getClient(): ClientInterface` has been replaced by `createRequest(): PendingRequest`, and `createRequest(array $body): Response` no longer exists. The Guzzle options are built in a separate `requestOptions(): array` method that you can override on its own.

Overrides of `handle()` and `failed(Throwable $exception)` must now declare a `void` return type to match the parent methods.

### `errorType` and `errorMessage` report Laravel's exceptions

For HTTP 4xx/5xx responses and connection failures, `$event->errorType` is now `Illuminate\Http\Client\RequestException` or `Illuminate\Http\Client\ConnectionException` instead of the Guzzle equivalents, and `$event->errorMessage` carries Laravel's message. Both are also filled in for any other exception that makes an attempt fail, where v3 left them `null`.

### Invalid JSON payloads throw exceptions

JSON encoding now uses `JSON_THROW_ON_ERROR`. Payloads containing invalid UTF-8, recursive arrays or other values that cannot be encoded throw `JsonException`.

For JSON request bodies and signed GET payloads, this happens before the job is dispatched. Handle these exceptions at the dispatch call, not in a failed-webhook listener. Unsigned JSON request bodies are also validated before dispatch, but unsigned GET query parameters do not require JSON encoding. v3 could sign or send an empty body after JSON encoding failed.

Raw bodies are not JSON-encoded. Custom jobs that set `outputType` to `RAW` must supply a string payload; an array throws `UnexpectedValueException` before dispatch.

### Delivery IDs and response-aware retries

The new `Webhook-Id` header contains the same UUID as webhook events and stays stable across attempts. Rename it with `webhook_id_header_name`, disable it by setting that key to `null`, or use `withWebhookIdHeader()` per call. Explicit caller headers take precedence case-insensitively. Neither this ID nor the optional timestamp is authenticated by the default signer.

`retryOn([...])`, `respectRetryAfter()` and `withJitter()` are opt-in; their global settings are `retryable_status_codes`, `respect_retry_after` and `retry_with_jitter`. An excluded HTTP status is immediately final, while connection failures still retry. `Retry-After` sets a minimum delay after jitter is applied. Ensure those delays fit your queue driver's limits, especially with SQS.

### Queued listeners can read response bodies and statistics

Webhook result events now serialize HTTP body snapshots instead of stream resources. `ShouldQueue` listeners receive reconstructed Laravel responses and Guzzle transfer statistics with readable bodies and preserved HTTP metadata. Synchronous listeners keep their original response objects; seekable streams retain their positions.

Webhook jobs and dispatching/result events preserve binary raw/custom bodies, headers, metadata and error messages using base64 encoding. Queue messages are larger and can include sensitive request and response data; encoding is not encryption. Check queue limits and access controls. Custom metadata, cookie jars and handler data must still be serializable; custom response subclasses and their additional runtime state are not preserved.

Non-seekable streams are read once and cached for shared response/statistics snapshots and repeated serialization. The first snapshot consumes the original stream. Buffer or replace it before events are dispatched if synchronous listeners need to read it too, or if middleware already consumed bytes that must be included.

### HTTP behavior that remains unchanged

Only a final 2xx response succeeds. Redirects are still followed using Guzzle's default redirect policy; a final 3xx response fails and is retried by default. Such a failure reports `Exception` with the message `Webhook call failed`.

The package preserves raw request bodies, configured Content-Type headers (including array values), GET query parameters, and Guzzle's cookie, connection-timeout and TLS defaults. Laravel's implicit cookie jar, ten-second connection timeout and TLS override are not applied. The configured total request timeout, SSL verification, proxy, client certificate, private key and transfer statistics are still passed through.

Laravel HTTP fakes, global options and global middleware now apply to webhook requests. The package's explicit transport settings override the corresponding global options. Headers use Laravel's merging behavior: global header values can be combined with webhook header values rather than replaced, and middleware can modify requests and responses. Review application-wide HTTP configuration when upgrading.

### The `Signature` header name is injected into `DefaultSigner`

`DefaultSigner` now receives the configured signature header name through its constructor. Resolve it from the container rather than instantiating it directly if you rely on the configured value.

### Exceptions extend `LogicException`

`CouldNotCallWebhook`, `InvalidBackoffStrategy`, `InvalidSigner` and `InvalidWebhookJob` now extend `LogicException` instead of `Exception`. Catching them by their own class name, or by `Exception`, keeps working.
