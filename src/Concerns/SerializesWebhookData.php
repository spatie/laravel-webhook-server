<?php

namespace Spatie\WebhookServer\Concerns;

use Illuminate\Queue\SerializesModels;

trait SerializesWebhookData
{
    use SerializesModels {
        __serialize as protected serializeModelProperties;
        __unserialize as protected restoreModelProperties;
    }

    /** @return array<array-key, mixed> */
    public function __serialize(): array
    {
        $properties = $this->serializeModelProperties();

        foreach ($properties as $name => $value) {
            $properties[$name] = match (true) {
                is_string($value), is_array($value) => base64_encode(serialize($value)),
                default => $value,
            };
        }

        return $properties;
    }

    /** @param array<array-key, mixed> $values */
    public function __unserialize(array $values): void
    {
        foreach ($values as $name => $value) {
            if (is_string($value)) {
                $values[$name] = unserialize(base64_decode($value));
            }
        }

        $this->restoreModelProperties($values);
    }
}
