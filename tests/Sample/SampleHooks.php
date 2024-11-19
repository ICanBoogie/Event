<?php

namespace Test\ICanBoogie\Sample;

use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

class SampleHooks
{
    public static function with_sender(BeforeActionEvent $event, SampleSender $sender): void
    {
    }

    public static function without_sender(SampleEvent $event): void
    {
    }
}
