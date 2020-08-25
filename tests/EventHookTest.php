<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use PHPUnit\Framework\TestCase;

class EventHookTest extends TestCase
{
	public function test_properties()
	{
		$events = $this
			->getMockBuilder(EventCollection::class)
			->disableOriginalConstructor()
			->getMock();

		/* @var $events EventCollection */

		$type = 'type' . uniqid();

		$hook = function() {};

		$eh = new EventHook($events, $type, $hook);

		$this->assertSame($events, $eh->events);
		$this->assertSame($type, $eh->type);
		$this->assertSame($hook, $eh->hook);
	}

	public function test_detach()
	{
		$type = 'type' . uniqid();

		$hook = function() {};

		$events = $this
			->getMockBuilder(EventCollection::class)
			->disableOriginalConstructor()
			->setMethods([ 'detach' ])
			->getMock();
		$events
			->expects($this->once())
			->method('detach')
			->with($type, $hook);

		/* @var $events EventCollection */

		(new EventHook($events, $type, $hook))->detach();
	}
}
