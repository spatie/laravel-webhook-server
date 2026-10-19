<?php

namespace Spatie\WebhookServer\Signer;

interface Signer
{
    public function signatureHeaderName(): string;

    /**
     * @param string $webhookUrl
     * @param array<array-key, mixed> $payload
     * @param string $secret
     */
    public function calculateSignature(string $webhookUrl, array $payload, string $secret): string;
}
