<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Sample\Processor;

use ICanBoogie\Event;
use Test\ICanBoogie\Sample\Processor;

class BeforeProcessEvent extends Event
{
    public array $values;

    public function __construct(Processor $sender, array &$values)
    {
        $this->values = &$values;

        parent::__construct($sender);
    }
}
