<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use Spatie\WebhookServer\Signer\Signer;

class UrlAwareSigner implements Signer
{
    public function signatureHeaderName(): string
    {
        return 'X-URL-Signature';
    }

    public function calculateSignature(string $webhookUrl, string $body, string $secret): string
    {
        return hash_hmac('sha256', "{$webhookUrl}\n{$body}", $secret);
    }
}
