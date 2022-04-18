<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie;

use ICanBoogie\Event;
use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\SampleTarget\BeforePracticeEvent;
use Test\ICanBoogie\SampleTarget\PracticeEvent;
use Traversable;

use function ICanBoogie\Event\qualify_type;

final class EventCollectionTest extends TestCase
{
	private EventCollection $events;

	protected function setUp(): void
	{
		$this->events = $events = new EventCollection;

		EventCollectionProvider::define(fn(): EventCollection => $events);
	}

	public function test_generic_event(): void
	{
		$type = 'type' . uniqid();
		$invoked = false;

		$this->events->attach($type, function (Event $event) use (&$invoked) {
			$invoked = true;
		});

		new Event(null, $type);

		$this->assertTrue($invoked);
	}

	public function test_detach_generic_event_hook(): void
	{
		$n = 0;
		$type = 'type' . uniqid();
		$hook = function (Event $event) use (&$n) {
			$n++;
		};

		$detach = $this->events->attach($type, $hook);
		new Event(null, $type);

		$detach();
		new Event(null, $type);

		$this->assertEquals(1, $n);
	}

	public function test_detach_event_hook(): void
	{
		$n = 0;
		$target = new SampleTarget();
		$type = 'type' . uniqid();
		$qualified_type = qualify_type($type, $target);
		$hook = function (Event $event, SampleTarget $t) use ($target, &$n) {
			$n++;
			$this->assertSame($target, $t);
		};

		$detach = $this->events->attach($qualified_type, $hook);
		new Event($target, $type);

		$detach();
		new Event($target, $type);

		$this->assertEquals(1, $n);
	}

	public function test_detach_typed_event_hook(): void
	{
		$n = 0;
		$target = new SampleTarget();
		$hook = function (BeforePracticeEvent $event, SampleTarget $target) use (&$n) {
			$n++;
		};

		$detach = $this->events->attach($hook);
		new BeforePracticeEvent($target);

		$detach();
		new BeforePracticeEvent($target);

		$this->assertEquals(1, $n);
	}

	public function test_detach_unattached_hook(): void
	{
		$this->expectException(\LogicException::class);
		$this->events->detach(
			SampleTarget::class . '::practice:before',
			function (BeforePracticeEvent $event, SampleTarget $target) {
			}
		);
	}

	/**
	 * @depends test_detach_typed_event_hook
	 */
	public function test_attach_to(): void
	{
		$target0 = new SampleTarget;
		$target1 = clone $target0;

		$invoked_count = 0;

		$this->events->attach_to(
			$target0,
			function (PracticeEvent $event, SampleTarget $target) use ($target0, &$invoked_count) {
				$this->assertSame($target0, $target);

				$invoked_count++;
			}
		);

		new PracticeEvent($target1);

		$this->assertEquals(0, $invoked_count);

		new PracticeEvent($target0);
		new PracticeEvent($target1);

		$this->assertEquals(1, $invoked_count);
	}

	public function test_once(): void
	{
		$invoked_count = 0;

		$this->events->once(function (PracticeEvent $event, SampleTarget $target) use (&$invoked_count) {
			$invoked_count++;
		});

		$target = new SampleTarget;

		new PracticeEvent($target);
		$this->assertEquals(1, $invoked_count);

		new PracticeEvent($target);
		new PracticeEvent($target);
		new PracticeEvent($target);
		$this->assertEquals(1, $invoked_count);
	}

	/**
	 * Should be able to attach many events.
	 * A same callable should only be attached once per event.
	 */
	public function test_attach_many(): void
	{
		$f1 = function () {
		};
		$f11 = function () {
		};
		$f2 = function () {
		};
		$f21 = function () {
		};
		$f3 = function () {
		};

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

	public function test_iterator(): void
	{
		$events = new EventCollection();

		$this->assertInstanceOf(Traversable::class, $events->getIterator());
	}
}
