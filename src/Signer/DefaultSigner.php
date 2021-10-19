<?php

namespace Spatie\WebhookServer\Signer;

class DefaultSigner implements Signer
{
    public function calculateSignature(string $webhookUrl, string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public function signatureHeaderName(): string
    {
        return config('webhook-server.signature_header_name');
    }
}
