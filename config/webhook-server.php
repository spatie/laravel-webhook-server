<?php

return [

    /*
     *  The default queue that should be used.
     */
    'queue' => 'default',

    /*
     * The default http verb to use.
     */
    'http_verb' => 'post',

    /*
     * If a call to a webhook takes longer that this amount of seconds
     * the attempt will be considered failed.
     */
    'timeout_in_seconds' => 3,

    /*
     * The amount of times the webhook should be called before we give up.
     */
    'tries' => 3,

    /*
     * This class determines how many seconds there should be between attempts.
     */
    'backoff_strategy' => \Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy::class,

    /*
     * This class is responsible for calculating the signature that will be added to
     * the headers of the webhook request. A webhook client can use the signature
     * to verify the request hasn't been tampered with.
     */
    'signer' => \Spatie\WebhookServer\Signer\DefaultSigner::class,

    /*
     * This is the name of the header where the signature will be added.
     */
    'signature_header_name' => 'Signature',

    /*
     * These are the headers that will be added to all webhook requests.
     */
    'headers' => [],

    /*
     * By default we will verify that the ssl certification of the destination
     * of the webhook is valid.
     */
    'verify_ssl' => true,
];

