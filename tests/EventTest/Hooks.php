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

use Test\ICanBoogie\SampleTarget;
use Test\ICanBoogie\SampleTarget\BeforePracticeEvent;

class Hooks
{
	static public function before_target_practice(BeforePracticeEvent $event, SampleTarget $target): void
	{

	}
}
