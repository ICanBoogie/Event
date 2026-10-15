<?php

namespace Test\ICanBoogie\Discovery;

use ICanBoogie\Event\Listener;
use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

class ListenerServiceWithoutSender
{
    #[Listener]
    public function __invoke(BeforeActionEvent $event): void
    {
    }
}
