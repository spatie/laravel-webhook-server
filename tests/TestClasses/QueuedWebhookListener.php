<?php

namespace Spatie\WebhookServer\Tests\TestClasses;

use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\WebhookServer\Events\DispatchingWebhookCallEvent;
use Spatie\WebhookServer\Events\WebhookCallEvent;

class QueuedWebhookListener implements ShouldQueue
{
    /** @var list<WebhookCallEvent|DispatchingWebhookCallEvent> */
    public static array $events = [];

    public function handle(WebhookCallEvent|DispatchingWebhookCallEvent $event): void
    {
        self::$events[] = $event;
    }
}
