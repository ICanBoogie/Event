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

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use LogicException;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\SampleTarget\BeforePracticeEvent;
use Test\ICanBoogie\SampleTarget\PracticeEvent;
use Traversable;

use function ICanBoogie\emit;

final class EventCollectionTest extends TestCase
{
	private EventCollection $events;

	protected function setUp(): void
	{
		$this->events = $events = new EventCollection;

		EventCollectionProvider::define(fn(): EventCollection => $events);
	}

	public function test_event_without_target(): void
	{
		$invoked = false;

		$this->events->attach(SampleEvent::class, function (SampleEvent $event) use (&$invoked) {
			$invoked = true;
		});

		emit(new SampleEvent());

		$this->assertTrue($invoked);
	}

	public function test_detach_without_target(): void
	{
		$n = 0;
		$hook = function (SampleEvent $event) use (&$n) {
			$n++;
		};

		$detach = $this->events->attach(SampleEvent::class, $hook);
		emit(new SampleEvent());

		$detach();
		emit(new SampleEvent());

		$this->assertEquals(1, $n);
	}

	public function test_detach_with_target(): void
	{
		$n = 0;
		$target = new SampleTarget();

		$detach = $this->events->attach(
			SampleEvent::for($target),
			function (SampleEvent $event, SampleTarget $t) use ($target, &$n) {
				$n++;
				$this->assertSame($target, $t);
			}
		);
		emit(new SampleEvent($target));

		$detach();
		emit(new SampleEvent($target));

		$this->assertEquals(1, $n);
	}

	public function test_detach_with_auto_hook(): void
	{
		$n = 0;
		$target = new SampleTarget();

		$detach = $this->events->attach(function (BeforePracticeEvent $event, SampleTarget $target) use (&$n) {
			$n++;
		});
		emit(new BeforePracticeEvent($target));

		$detach();
		emit(new BeforePracticeEvent($target));

		$this->assertEquals(1, $n);
	}

	public function test_detach_unattached_hook(): void
	{
		$this->expectException(LogicException::class);
		$this->events->detach(
			BeforePracticeEvent::for(SampleTarget::class),
			function (BeforePracticeEvent $event, SampleTarget $target) {
			}
		);
	}

	/**
	 * @depends test_detach_with_auto_hook
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

		emit(new PracticeEvent($target1));

		$this->assertEquals(0, $invoked_count);

		emit(new PracticeEvent($target0));
		emit(new PracticeEvent($target1));

		$this->assertEquals(1, $invoked_count);
	}

	public function test_once(): void
	{
		$invoked_count = 0;

		$this->events->once(function (PracticeEvent $event, SampleTarget $target) use (&$invoked_count) {
			$invoked_count++;
		});

		$target = new SampleTarget;

		emit(new PracticeEvent($target));
		$this->assertEquals(1, $invoked_count);

		emit(new PracticeEvent($target));
		emit(new PracticeEvent($target));
		emit(new PracticeEvent($target));
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
