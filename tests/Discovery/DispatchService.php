<?php

namespace Test\ICanBoogie\Discovery;

use ICanBoogie\Event\Listener;

final class DispatchService
{
    #[Listener]
    public function __invoke(ShippedEvent $event): void
    {
    }
}
