<?php

return [

    'queue' => 'default',

    'http_verb' => 'post',

    'timeout_in_seconds' => 3,

    'tries' => 3,

    'backoff_strategy' => \Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy::class,

    'signer' => \Spatie\WebhookServer\Signer\DefaultSigner::class,

    'signature_header_name' => 'Signature',

    'headers' => [],

    'verify_ssl' => true,




];

