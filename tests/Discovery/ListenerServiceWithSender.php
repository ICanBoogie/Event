<?php

namespace Test\ICanBoogie\Discovery;

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
