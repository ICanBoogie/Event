<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Sample\SampleSender;

use ICanBoogie\Event;
use Test\ICanBoogie\Sample\SampleSender;

class BeforeActionEvent extends Event
{
    public function __construct(SampleSender $sender)
    {
        parent::__construct($sender);
    }
}
