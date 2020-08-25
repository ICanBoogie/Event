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

use ICanBoogie\EventTest\Target;
use PHPUnit\Framework\TestCase;

class EventCollectionTest extends TestCase
{
	/**
	 * @var EventCollection
	 */
	private $events;

	protected function setUp(): void
	{
		$this->events = $events = new EventCollection;

		EventCollectionProvider::define(function () use ($events) { return $events; });
	}

	public function test_generic_event()
	{
		$type = 'type' . uniqid();
		$invoked = false;

		$this->events->attach($type, function(Event $event) use (&$invoked) {

			$invoked = true;

		});

		new Event(null, $type);

		$this->assertTrue($invoked);
	}

	public function test_detach_generic_event_hook()
	{
		$type = 'type' . uniqid();
		$hook = function(Event $event) {

			$this->fail("Should not be invoked");

		};

		$this->events->attach($type, $hook);
		$this->events->detach($type, $hook);

		new Event(null, $type);
	}

	public function test_detach_event_hook()
	{
		$type = 'type' . uniqid();

		$hook = function(Event $event, Target $target) {

			$this->fail("Should not be invoked");

		};

		$this->events->attach($type, $hook);
		$this->events->detach($type, $hook);

		new Event(new Target, $type);
	}

	public function test_detach_typed_event_hook()
	{
		$hook = function(Target\BeforePracticeEvent $event, Target $target) {

			$this->fail("Should not be invoked");

		};

		$this->events->attach($hook);
		$this->events->detach('ICanBoogie\EventTest\Target::practice:before', $hook);

		new Target\BeforePracticeEvent(new Target);
	}

	public function test_detach_unattached_hook()
	{
		$this->expectException(\LogicException::class);
		$this->events->detach(Target::class . '::practice:before', function(Target\BeforePracticeEvent $event, Target $target) {});
	}

	/**
	 * @depends test_detach_typed_event_hook
	 */
	public function test_attach_to()
	{
		$target0 = new Target;
		$target1 = clone $target0;

		$invoked_count = 0;

		$this->events->attach_to($target0, function(Target\PracticeEvent $event, Target $target) use ($target0, &$invoked_count) {

			$this->assertSame($target0, $target);

			$invoked_count++;

		});

		new Target\PracticeEvent($target1);

		$this->assertEquals(0, $invoked_count);

		new Target\PracticeEvent($target0);
		new Target\PracticeEvent($target1);

		$this->assertEquals(1, $invoked_count);
	}

	public function test_once()
	{
		$invoked_count = 0;

		$this->events->once(function(Target\PracticeEvent $event, Target $target) use (&$invoked_count) {

			$invoked_count++;

		});

		$target = new Target;

		new Target\PracticeEvent($target);
		$this->assertEquals(1, $invoked_count);

		new Target\PracticeEvent($target);
		new Target\PracticeEvent($target);
		new Target\PracticeEvent($target);
		$this->assertEquals(1, $invoked_count);
	}

	/**
	 * Should be able to attach many events.
	 * A same callable should only be attached once per event.
	 */
	public function test_attach_many()
	{
		$f1 = function () {};
		$f11 = function () {};
		$f2 = function () {};
		$f21 = function () {};
		$f3 = function () {};

		$events = new EventCollection([

			'one' => [ $f1 ],
			'two' => [ $f2 ]

		]);

		$events->attach_many([

			'one' => [ $f1, $f11 ],
			'two' => [ $f21 ],
			'three' => [ $f3 ]

		]);

		$this->assertSame([

			'one' => [ $f1, $f11 ],
			'two' => [ $f2, $f21 ],
			'three' => [ $f3 ],

		], iterator_to_array($events));
	}

	public function test_iterator()
	{
		$events = new EventCollection;

		$this->assertInstanceOf(\ArrayIterator::class, $events->getIterator());
	}
}
