<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use JsonSerializable;

class ChangingPayload implements JsonSerializable
{
    public int $value = 0;

    public function jsonSerialize(): array
    {
        return ['value' => ++$this->value];
    }
}
