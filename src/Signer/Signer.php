<?php

namespace Spatie\WebhookServer\Signer;

interface Signer
{
    public function signatureHeaderName(): string;

    public function calculateSignature(array $payload, string $secret): string;
}
