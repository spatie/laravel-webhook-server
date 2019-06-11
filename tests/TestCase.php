<?php

namespace Spatie\WebhookServer\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\WebhookServer\WebhookServerServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            WebhookServerServiceProvider::class,
        ];
    }
}

