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

    public function calculateSignature(string $webhookUrl, string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    public function signatureHeaderName(): string
    {
        return $this->signatureHeaderName;
    }
}
