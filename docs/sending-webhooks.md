---
title: Sending webhooks
weight: 3
---

## Send a JSON payload

```php
use Spatie\WebhookServer\WebhookCall;

WebhookCall::create()
    ->url('https://other-app.com/webhooks')
    ->payload(['key' => 'value'])
    ->useSecret('your-shared-secret')
    ->dispatch();
```

The payload is encoded once before dispatch using `JSON_THROW_ON_ERROR`. The package signs and sends those exact bytes on every attempt. Invalid JSON payloads throw `JsonException` at the dispatch call, including when signing is disabled.

## Send synchronously or conditionally

Use `dispatchSync()` to make the request immediately instead of putting it on the queue. Synchronous calls do not wait or loop through retries.

The methods `dispatchIf($condition)`, `dispatchUnless($condition)`, `dispatchSyncIf($condition)` and `dispatchSyncUnless($condition)` conditionally send a webhook.

## Change the HTTP verb and headers

The default verb is `post`, configured with `http_verb`. Override it with `useHttpVerb('put')`, or send a GET request with `useHttpVerb('get')`.

GET webhooks send the payload as query parameters and sign its JSON representation, not an empty body. Unsigned GET query parameters are not JSON-encoded.

Add default headers through the `headers` config key, or use `withHeaders()` for a single call:

```php
WebhookCall::create()
    ->url('https://other-app.com/webhooks')
    ->payload(['key' => 'value'])
    ->useSecret('your-shared-secret')
    ->useHttpVerb('put')
    ->withHeaders(['X-Source' => 'billing'])
    ->dispatch();
```

Laravel HTTP global options and middleware apply to these requests. Global headers can be combined with webhook headers instead of replaced. Middleware that changes a signed body must also update its signature.

## Send a raw body

Use `sendRawBody()` instead of `payload()` to send XML, pre-encoded JSON, or another string. Raw bodies can be signed, including binary strings. Set a matching Content-Type for your receiver:

```php
WebhookCall::create()
    ->url('https://other-app.com/webhooks')
    ->sendRawBody('<root>someXMLContent</root>')
    ->withHeaders(['Content-Type' => 'application/xml'])
    ->useSecret('your-shared-secret')
    ->dispatch();
```

Event payloads are strings for raw calls and arrays for JSON calls.

## Delivery IDs

Every request includes a `Webhook-Id` header containing the webhook UUID. It stays the same across retries. Retrieve it before dispatch with `getUuid()`, or set an application-specific ID using `uuid('your-delivery-id')`.

Set `webhook_id_header_name` to rename the header globally, or set it to `null` to disable it. Per call, use `withWebhookIdHeader('X-Delivery-Id')` or `withWebhookIdHeader(null)`. An explicitly supplied header with that name takes precedence, regardless of casing.

The default signer authenticates the body only, not the ID or other headers. A delivery ID is not replay protection: authenticate the request and make processing idempotent on the receiver.

## Timestamps

Call `useTimestamp()` to include a `Timestamp` header containing the Unix timestamp for the attempt. Rename it using `timestamp_header_name` in the config file.

The default signer does not authenticate this header. For replay protection, put a timestamp in the signed payload and have the receiver validate its age.

## Metadata and Horizon tags

Add local context with `meta(['order_id' => 123])`. Metadata is available to [event listeners](/docs/laravel-webhook-server/v4/events), but is not sent to the receiver.

Set Laravel Horizon tags using the `tags` config key or `withTags(['webhooks', 'billing'])`.
