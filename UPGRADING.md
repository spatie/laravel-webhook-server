# Upgrading

## From v3 to v4

Webhooks are now sent with Laravel's HTTP client instead of a Guzzle client that the package built itself.

### Requirements

v4 requires PHP 8.4 and Laravel 13. Stay on v3 if you are on an older version.

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

### `errorType` and `errorMessage` report Laravel's exceptions

For a failed attempt, `$event->errorType` is now `Illuminate\Http\Client\RequestException` or `Illuminate\Http\Client\ConnectionException` instead of the Guzzle equivalents, and `$event->errorMessage` carries Laravel's message. Both are also filled in for any other exception that makes an attempt fail, where v3 left them `null`.

### The `Signature` header name is injected into `DefaultSigner`

`DefaultSigner` now receives the configured signature header name through its constructor. Resolve it from the container rather than instantiating it directly if you rely on the configured value.

### Exceptions extend `LogicException`

`CouldNotCallWebhook`, `InvalidBackoffStrategy`, `InvalidSigner` and `InvalidWebhookJob` now extend `LogicException` instead of `Exception`. Catching them by their own class name, or by `Exception`, keeps working.
