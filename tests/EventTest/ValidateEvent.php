<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\EventTest;

use ICanBoogie\Event;

/**
 * Event class for the `Test\A::validate` event.
 */
class ValidateEvent extends Event
{
    public array $values;
    public bool $valid;

    public function __construct(SampleA $target, array $values, bool &$valid)
    {
        $this->values = $values;
        $this->valid = &$valid;

        parent::__construct($target);
    }
}
