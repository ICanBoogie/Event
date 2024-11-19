<?php

namespace Test\ICanBoogie\Acme;

use ICanBoogie\Event\Listener;
use Test\ICanBoogie\Sample\SampleSender;
use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

class ListenerServiceWithSender
{
    #[Listener]
    public function __invoke(BeforeActionEvent $event, SampleSender $sender): void
    {
    }
}
