<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\SampleTarget;

use ICanBoogie\Event;
use Test\ICanBoogie\SampleTarget;

class BeforePracticeEvent extends Event
{
	public function __construct(SampleTarget $target)
	{
		parent::__construct($target);
	}
}
