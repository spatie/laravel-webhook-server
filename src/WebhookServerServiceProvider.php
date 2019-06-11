<?php

namespace Spatie\WebhookServer;

use Illuminate\Support\ServiceProvider;

class WebhookServerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webhook-server.php' => config_path('webhook-server.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webhook-server.php', 'webhook-server');
    }
}
