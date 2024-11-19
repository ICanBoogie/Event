<?php

namespace Test\ICanBoogie\Sample;

use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

class SampleCallableWithSender
{
    public function __invoke(BeforeActionEvent $event, SampleSender $sender): void
    {
    }
}
