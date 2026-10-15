<?php

namespace Test\ICanBoogie\Discovery;

use ICanBoogie\Event\Listener;

final class StaticListeners
{
    #[Listener]
    public static function on_shipped(ShippedEvent $event): void
    {
    }

    #[Listener]
    public static function on_shipped_by_courier(ShippedEvent $event, Courier $sender): void
    {
    }

    #[Listener]
    public static function on_shipped_by_trackable(ShippedEvent $event, Trackable $sender): void
    {
    }
}
