---
title: Transport options
weight: 6
---

## Proxies

Configure `proxy` globally or use `useProxy('http://proxy.server:3128')` per call. The value follows [Guzzle's proxy format](https://docs.guzzlephp.org/en/stable/request-options.html#proxy), including arrays for different schemes and excluded hosts.

## Mutual TLS

Mutual TLS authenticates both parties: your application verifies the endpoint's server certificate, and the endpoint verifies your application's client certificate.

```php
WebhookCall::create()
    ->url('https://other-app.com/webhooks')
    ->payload(['event' => 'order.created'])
    ->useSecret('your-shared-secret')
    ->mutualTls(
        certPath: storage_path('certificates/client.pem'),
        sslKeyPath: storage_path('certificates/client-key.pem'),
        certPassphrase: 'optional-certificate-passphrase',
        sslKeyPassphrase: 'optional-key-passphrase',
    )
    ->dispatch();
```

Omit either passphrase when the corresponding file is not encrypted. To supply a custom CA bundle for verifying the receiver, call `verifySsl(storage_path('certificates/ca.pem'))`.

## TLS verification

HTTPS certificates are verified by default. A verification failure fails the attempt. Avoid disabling verification; if necessary, set `verify_ssl` to `false` or call `doNotVerifySsl()` for a single webhook.

## Laravel HTTP configuration

Laravel HTTP fakes, global options and global middleware apply to webhook requests. The package's explicit transport settings override the corresponding global options. Global headers can be combined with webhook headers rather than replaced.

The package retains Guzzle's cookie, connection-timeout, redirect and TLS defaults rather than Laravel's implicit cookie jar, ten-second connection timeout and TLS override. Total request timeout, SSL verification, proxy, certificate, private key and transfer statistics remain configurable.

Binding `GuzzleHttp\Client` in Laravel's container no longer swaps the webhook client. Use Laravel HTTP global middleware, or extend `CallWebhookJob` and override `createRequest(): PendingRequest` or `requestOptions(): array` when you need a package-specific transport customization.
