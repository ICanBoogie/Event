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
 * Event class for the `Test\A::process:before` event.
 */
class BeforeProcessEvent extends Event
{
    public const TYPE = 'process:before';

    public array $values;

    public function __construct(SampleA $target, array &$values)
    {
        $this->values = &$values;

        parent::__construct($target, self::TYPE);
    }
}
