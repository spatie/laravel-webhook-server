---
title: Installation and setup
weight: 2
---

This version requires PHP 8.4 and Laravel 13. Use [v3](https://github.com/spatie/laravel-webhook-server/tree/3.11.0) if your application uses an older version. Existing users should read [the upgrading guide](/docs/laravel-webhook-server/v4/upgrading) before deploying v4.

Install the package using Composer:

```bash
composer require spatie/laravel-webhook-server:^4.0
```

The service provider is registered automatically. Publish the configuration with:

```bash
php artisan vendor:publish --provider="Spatie\WebhookServer\WebhookServerServiceProvider"
```

The published `config/webhook-server.php` controls the queue, HTTP defaults, signing, delivery ID header, retry policy, TLS verification and Horizon tags. See [the complete configuration file](https://github.com/spatie/laravel-webhook-server/blob/main/config/webhook-server.php).

## Configure a queue worker

Use a real queue connection, such as `database` or `redis`, in production. Set up the connection according to [Laravel's queue documentation](https://laravel.com/docs/13.x/queues) and run a worker:

```bash
php artisan queue:work
```

The `sync` connection does not wait or loop through delayed retries. The package schedules retries using Laravel's delayed queue jobs.

Choose a queue and connection globally using the `queue` and `connection` configuration keys, or override them per call:

```php
WebhookCall::create()
    ->url('https://other-app.com/webhooks')
    ->payload(['event' => 'order.created'])
    ->useSecret('your-shared-secret')
    ->onConnection('redis')
    ->onQueue('webhooks')
    ->dispatch();
```
