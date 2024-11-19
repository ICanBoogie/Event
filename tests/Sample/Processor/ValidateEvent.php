<?php

namespace Test\ICanBoogie\Sample\Processor;

use ICanBoogie\Event;
use Test\ICanBoogie\Sample\Processor;

class ValidateEvent extends Event
{
    /**
     * @param mixed[] $values
     */
    public function __construct(
        Processor $sender,
        public array $values,
        public bool &$valid,
    ) {
        parent::__construct($sender);
    }
}
