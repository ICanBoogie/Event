<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Sample;

use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

class SampleHooks
{
	static public function with_sender(BeforeActionEvent $event, SampleSender $sender): void
	{

	}

	static public function without_sender(SampleEvent $event): void
	{

	}
}
