<?php

namespace Test\ICanBoogie\Sample\Processor;

use ICanBoogie\Event;
use Test\ICanBoogie\Sample\Processor;

class BeforeProcessEvent extends Event
{
    /**
     * @param mixed[] $values
     */
    public function __construct(
        Processor $sender,
        public array &$values,
    ) {
        parent::__construct($sender);
    }
}
