<?php

namespace Test\ICanBoogie\Acme;

use ICanBoogie\Event\Listen;
use Test\ICanBoogie\Sample\SampleSender;
use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

class ListenerServiceWithSender
{
    #[Listen]
    public function __invoke(BeforeActionEvent $event, SampleSender $sender): void
    {
    }
}
