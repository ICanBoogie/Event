<?php

namespace Test\ICanBoogie\Acme;

use ICanBoogie\Event\Listen;
use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

class ListenerServiceWithoutSender
{
    #[Listen]
    public function __invoke(BeforeActionEvent $event): void
    {
    }
}
