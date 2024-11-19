<?php

namespace Test\ICanBoogie\Acme;

use ICanBoogie\Event\Listener;
use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

class ListenerServiceWithoutSender
{
    #[Listener]
    public function __invoke(BeforeActionEvent $event): void
    {
    }
}
