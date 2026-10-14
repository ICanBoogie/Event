<?php

namespace Test\ICanBoogie\Sample;

use ICanBoogie\Event;
use Throwable;

/**
 * An event sent by an exception, like `RecoverEvent` in icanboogie/http.
 */
class ThrownEvent extends Event
{
    public function __construct(Throwable $sender)
    {
        parent::__construct($sender);
    }
}
