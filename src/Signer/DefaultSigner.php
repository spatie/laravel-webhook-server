<?php

namespace Spatie\WebhookServer\Signer;

use Illuminate\Container\Attributes\Config;

class DefaultSigner implements Signer
{
    public function __construct(
        #[Config('webhook-server.signature_header_name')]
        protected string $signatureHeaderName = 'Signature',
    ) {
    }

    public function calculateSignature(string $webhookUrl, array $payload, string $secret): string
    {
        $payloadJson = json_encode($payload);

        return hash_hmac('sha256', $payloadJson, $secret);
    }

    public function signatureHeaderName(): string
    {
        return $this->signatureHeaderName;
    }
}
