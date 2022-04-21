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
use Test\ICanBoogie\Sample\SampleEvent;
use Test\ICanBoogie\Sample\SampleSender;
use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;
use Test\ICanBoogie\Sample\SampleSender\ActionEvent;
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

	public function test_event_without_sender(): void
	{
		$invoked = false;

		$this->events->attach(SampleEvent::class, function (SampleEvent $event) use (&$invoked) {
			$invoked = true;
		});

		emit(new SampleEvent());

		$this->assertTrue($invoked);
	}

	public function test_detach_without_sender(): void
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

	public function test_detach_with_sender(): void
	{
		$n = 0;
		$sender = new SampleSender();

		$detach = $this->events->attach(
			ActionEvent::for($sender),
			function (ActionEvent $event, SampleSender $t) use ($sender, &$n) {
				$n++;
				$this->assertSame($sender, $t);
			}
		);
		emit(new ActionEvent($sender));

		$detach();
		emit(new ActionEvent($sender));

		$this->assertEquals(1, $n);
	}

	public function test_detach_with_auto_hook(): void
	{
		$n = 0;
		$sender = new SampleSender();

		$detach = $this->events->attach(function (BeforeActionEvent $event, SampleSender $sender) use (&$n) {
			$n++;
		});
		emit(new BeforeActionEvent($sender));

		$detach();
		emit(new BeforeActionEvent($sender));

		$this->assertEquals(1, $n);
	}

	public function test_detach_unattached_hook(): void
	{
		$this->expectException(LogicException::class);
		$this->events->detach(
			BeforeActionEvent::for(SampleSender::class),
			function (BeforeActionEvent $event, SampleSender $sender) {
			}
		);
	}

	/**
	 * @depends test_detach_with_auto_hook
	 */
	public function test_attach_to(): void
	{
		$sender0 = new SampleSender();
		$sender1 = clone $sender0;

		$invoked_count = 0;

		$this->events->attach_to(
			$sender0,
			function (ActionEvent $event, SampleSender $sender) use ($sender0, &$invoked_count) {
				$this->assertSame($sender0, $sender);

				$invoked_count++;
			}
		);

		emit(new ActionEvent($sender1));

		$this->assertEquals(0, $invoked_count);

		emit(new ActionEvent($sender0));
		emit(new ActionEvent($sender1));

		$this->assertEquals(1, $invoked_count);
	}

	public function test_once(): void
	{
		$invoked_count = 0;

		$this->events->once(function (ActionEvent $event, SampleSender $sender) use (&$invoked_count) {
			$invoked_count++;
		});

		$sender = new SampleSender();

		emit(new ActionEvent($sender));
		$this->assertEquals(1, $invoked_count);

		emit(new ActionEvent($sender));
		emit(new ActionEvent($sender));
		emit(new ActionEvent($sender));
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
