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
use Test\ICanBoogie\EventTest\SampleA;
use Test\ICanBoogie\EventTest\SampleB;
use Test\ICanBoogie\EventTest\BeforeProcessEvent;
use Test\ICanBoogie\EventTest\ProcessEvent;
use Test\ICanBoogie\EventTest\ValidateEvent;

use function ICanBoogie\emit;

final class EventTest extends TestCase
{
	private EventCollection $events;

	protected function setUp(): void
	{
		$this->events = new EventCollection();

		EventCollectionProvider::define(fn() => $this->events);
	}

	public function test_qualify(): void
	{
		$this->assertEquals(
			"Test\ICanBoogie\SampleTarget::process:before",
			BeforeProcessEvent::qualify(SampleTarget::class)
		);
	}

	public function test_stop(): void
	{
		$n = 0;
		$type = 'event-' . uniqid();

		$this->events->attach($type, function (Event $event) use (&$n) {
			$n++;
		});

		$this->events->attach($type, function (Event $event) {
			$event->stop();
		});

		$event = emit(new Event(null, $type));

		$this->assertTrue($event->stopped);
		$this->assertEquals(0, $n);
	}

	public function test_target(): void
	{
		$target = new SampleTarget;
		$event = emit(new Event($target, uniqid()));

		$this->assertSame($target, $event->target);
	}

	/**
	 * The big test, with reflection and chains.
	 */
	public function testEventHooks(): void
	{
		/*
		 * The A::validate() method would return false if the following hook wasn't called.
		 */
		$this->events->attach(function (ValidateEvent $event, SampleA $target) {
			$event->valid = true;
		});

		/*
		 * We add "three" to the values of A instances before they are processed.
		 */
		$this->events->attach(function (BeforeProcessEvent $event, SampleA $target) {
			$event->values['three'] = 3;
		});

		/*
		 * This hook is called before any hook set on the A class, because we want "four" to be
		 * after "three", which is added by the hook above, we use the _chain_ feature of the event.
		 *
		 * Hooks pushed by the chain() method are executed after the even chain was processed.
		 */
		$this->events->attach(function (BeforeProcessEvent $event, SampleB $target) {
			$event->chain(function ($event) {
				$event->values['four'] = 4;
			});
		});

		/*
		 * 10 is added to all processed values of A instances.
		 */
		$this->events->attach(function (ProcessEvent $event, SampleA $target) {
			array_walk($event->values, function (&$v) {
				$v += 10;
			});
		});

		/*
		 * We want processed values to be mutiplied by 10 for B instances, because 10 is already added to
		 * values of A instances we need to stop the event from propagating.
		 *
		 * The stop() method of the event breaks the event chain, so our hook will be the last
		 * called in the chain.
		 */
		$this->events->attach(function (ProcessEvent $event, SampleB $target) {
			array_walk($event->values, function (&$v) {
				$v *= 10;
			});

			$event->stop();
		});

		$initial_array = [ 'one' => 1, 'two' => 2 ];

		$a = new SampleA();
		$b = new SampleB();

		$a_processed = $a($initial_array);
		$b_processed = $b($initial_array);

		$this->assertEquals([ 'one' => 11, 'two' => 12, 'three' => 13 ], $a_processed);
		$this->assertEquals('one,two,three', implode(',', array_keys($a_processed)));

		$this->assertEquals([ 'one' => 10, 'two' => 20, 'three' => 30, 'four' => 40, 'five' => 50 ], $b_processed);
		$this->assertEquals('one,two,three,four,five', implode(',', array_keys($b_processed)));
	}
}
