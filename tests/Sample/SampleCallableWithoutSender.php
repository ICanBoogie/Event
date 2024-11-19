<?php

namespace Test\ICanBoogie\Sample;

class SampleCallableWithoutSender
{
    public function __invoke(SampleEvent $event): void
    {
    }
}
