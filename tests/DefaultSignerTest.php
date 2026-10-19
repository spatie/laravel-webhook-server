<?php

use Spatie\WebhookServer\Signer\DefaultSigner;

it('can calculate a signature for a given payload and secret', function () {
    $signer = new DefaultSigner();

    $signature = $signer->calculateSignature('https://my.app/webhooks', ['a' => '1'], 'abc');

    expect($signature)->toEqual('345611a3626cf5e080a7a412184001882ab231b8bdb465dc76dbf709f01f210a');
});

it('can return the name of the signature header', function () {
    config()->set('webhook-server.signature_header_name', 'X-Custom-Signature');

    expect(app(DefaultSigner::class)->signatureHeaderName())->toEqual('X-Custom-Signature');
});
