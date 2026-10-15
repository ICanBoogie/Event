<?php

namespace Test\ICanBoogie\Discovery;

use ICanBoogie\Event\Listener;

final class AuditService
{
    #[Listener(ref: 'audit')]
    public function __invoke(ShippedEvent $event, Courier $sender): void
    {
    }
}
