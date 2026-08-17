<?php

namespace Spatie\WebhookServer\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\WebhookServer\WebhookServerServiceProvider;

class TestCase extends Orchestra
{
    /** The Guzzle options of every request that was sent, in the order they were sent. */
    public array $sentOptions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();

        Http::preventStrayRequests();
    }

    /**
     * Fake the webhook endpoint and record the Guzzle options of every request. Those options
     * carry everything the request is configured with beyond headers and body: the timeout,
     * the certificates, the proxy and the SSL verification settings.
     */
    public function fakeWebhookEndpoint(int $status = 200): void
    {
        Http::fake([
            '*' => function (Request $request, array $options) use ($status) {
                $this->sentOptions[] = $options;

                return Http::response(status: $status);
            },
        ]);
    }

    public function sentOptions(int $index = 0): array
    {
        return $this->sentOptions[$index];
    }

    protected function getPackageProviders($app): array
    {
        return [
            WebhookServerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
}
