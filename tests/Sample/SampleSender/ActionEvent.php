<?php

namespace Test\ICanBoogie\Sample\SampleSender;

use ICanBoogie\Event;
use Test\ICanBoogie\Sample\SampleSender;

class ActionEvent extends Event
{
    public function __construct(SampleSender $sender)
    {
        parent::__construct($sender);
    }
}
