---
title: Events
weight: 7
---

The package fires these events in the `Spatie\WebhookServer\Events` namespace:

- `DispatchingWebhookCallEvent`: before dispatching the webhook to the queue. Synchronous dispatch does not fire this event.
- `WebhookCallSucceededEvent`: the receiver returned a final `2xx` response.
- `WebhookCallFailedEvent`: an attempt failed, whether or not another attempt will follow.
- `FinalWebhookCallFailedEvent`: no further attempts will be made.

All events expose `httpVerb`, `webhookUrl`, `payload`, `headers`, `meta`, `tags`, and `uuid`. The UUID stays the same across attempts. Headers include the signature when signing is enabled. Raw payloads are strings; JSON payloads are arrays.

Except for `DispatchingWebhookCallEvent`, events also expose:

- `attempt`: the attempt number.
- `response`: an `Illuminate\Http\Client\Response`, or `null` when no response was received.
- `errorType` and `errorMessage`: the exception class and message, or `null` for a successful attempt.
- `transferStats`: a `GuzzleHttp\TransferStats` instance, or `null`.

Use `$event->response?->status()`, `body()`, `json()`, or `headers()` to read the HTTP result. HTTP errors report Laravel's `RequestException`; connection failures report its `ConnectionException`.

## Queued listeners

Listeners may implement Laravel's `ShouldQueue` interface. HTTP bodies, status codes, header arrays, cookies and transfer statistics survive queue serialization, including the request and response inside the statistics. Listeners receive reconstructed Laravel responses and Guzzle statistics; synchronous listeners keep their original objects.

Webhook jobs and dispatching/result events use base64 encoding to preserve binary raw/custom bodies, headers, metadata and error messages through Laravel's JSON queue payloads. This increases message sizes and is not encryption. Check your queue's limits and protect access to sensitive request and response data. Custom metadata, cookie jars and handler data must still be serializable. Custom response subclasses and their additional runtime state are not preserved.

Seekable streams retain their original positions. Non-seekable streams are read once and cached while the original stream exists, preserving identical bytes across shared statistics and repeated serialization. The first snapshot consumes the original stream; buffer or replace it before dispatching events if synchronous listeners also need to read it. Bytes already consumed by middleware cannot be recovered.

## Handling delivery failures

Listen to `WebhookCallFailedEvent` for every failed attempt, or `FinalWebhookCallFailedEvent` to act only when delivery has stopped. The package does not log every delivery exception for you.

Enable `throw_exception_on_failure` or call `throwExceptionOnFailure()` to mark the underlying queue job as failed and rethrow its exception on the final failure.
