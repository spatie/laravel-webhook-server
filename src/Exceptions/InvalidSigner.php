<?php

namespace Spatie\WebhookServer\Exceptions;

use LogicException;
use Spatie\WebhookServer\Signer\Signer;

class InvalidSigner extends LogicException
{
    public static function doesNotImplementSigner(string $invalidClassName): self
    {
        $signerInterface = Signer::class;

        return new static("`{$invalidClassName}` is not a valid signer class because it does not implement `$signerInterface`");
    }
}
