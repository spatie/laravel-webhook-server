<?php

namespace Spatie\WebhookServer\Tests;

use Spatie\WebhookServer\Signer\DefaultSigner;

class DefaultSignerTest extends TestCase
{
    /** @test */
    public function it_can_calculate_a_signature_for_a_given_payload_and_secret()
    {
        $signer = new DefaultSigner();

        $signature = $signer->calculateSignature(['a' => '1'], 'abc');

        $this->assertEquals(
            '345611a3626cf5e080a7a412184001882ab231b8bdb465dc76dbf709f01f210a',
            $signature,
        );
    }

    /** @test */
    public function it_can_return_the_name_of_the_signature_header()
    {
        $signer = new DefaultSigner();

        $this->assertEquals(
            config('webhook-server.signature_header_name'),
            $signer->signatureHeaderName(),
        );
    }
}
