---
title: Signing requests
weight: 4
---

Share a secret between your application and the receiving application. Pass it to `useSecret()` when creating a webhook. The package adds a `Signature` header calculated over the prepared body:

```php
$signature = hash_hmac('sha256', $body, $secret);
```

For JSON requests, the package JSON-encodes the payload once before dispatch. Raw bodies are not JSON-encoded. Every retry sends the same bytes and signature. Standard JSON payloads retain the same default signature as v3.

The receiver should calculate the expected signature over the raw received body and compare it using `hash_equals()`. The default signer does not authenticate the URL, delivery ID, timestamp header, or other headers.

GET webhooks are an exception to body signing: the payload is sent as query parameters and its JSON representation is signed.

## Change or disable the signature header

Change `signature_header_name` in the config file to rename the header. If you need to send an unsigned webhook, call `doNotSign()`; no secret is required and the package does not add its signature header.

## Custom signers

Implement `Spatie\WebhookServer\Signer\Signer`:

```php
namespace App\WebhookSigners;

use Spatie\WebhookServer\Signer\Signer;

class CustomSigner implements Signer
{
    public function signatureHeaderName(): string
    {
        return 'X-Custom-Signature';
    }

    public function calculateSignature(string $webhookUrl, string $body, string $secret): string
    {
        return hash_hmac('sha256', "{$webhookUrl}\n{$body}", $secret);
    }
}
```

Set the `signer` config key to your class, or select it per call with `signUsing(CustomSigner::class)`.

The body is already serialized. Do not JSON-encode it again. Resolve `DefaultSigner` from Laravel's container if you use it directly and want its configured header name.

## Customize body serialization

To use different JSON options or canonical JSON, extend `Spatie\WebhookServer\CallWebhookJob`, override its protected `generateBody(): string` method, and configure your class in `webhook_job`.

The package prepares that body before dispatch, signs the result, and sends it unchanged across attempts. The override must not depend on worker-only state or the current attempt. HTTP middleware that later changes the body must also update the signature.
