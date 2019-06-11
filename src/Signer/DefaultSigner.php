<?php

namespace Spatie\WebhookServer\Signer;

class DefaultSigner implements Signer
{
    public function calculateSignature(array $payload, string $secret): string
    {
        $payloadJson = json_encode($payload);

        return hash_hmac('sha256', $payloadJson, $secret);
    }

    public function signatureHeaderName(): string
    {
        return config('webhook-server.signature_header_name');
    }
}
