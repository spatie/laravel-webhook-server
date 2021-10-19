<?php

namespace Spatie\WebhookServer\Signer;

interface Signer
{
    public function signatureHeaderName(): string;

    public function calculateSignature(string $webhookUrl, string $payload, string $secret): string;
}
