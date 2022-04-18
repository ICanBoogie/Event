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
use ICanBoogie\PropertyIsReserved;
use Test\ICanBoogie\EventTest\A;
use Test\ICanBoogie\EventTest\B;
use Test\ICanBoogie\EventTest\BeforeProcessEvent;
use Test\ICanBoogie\EventTest\ProcessEvent;
use Test\ICanBoogie\EventTest\ValidateEvent;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\SampleTarget\BeforePracticeEvent;

final class EventTest extends TestCase
{
	private EventCollection $events;

	protected function setUp(): void
	{
		$this->events = $events = new EventCollection;

		EventCollectionProvider::define(function () use ($events) { return $events; });
	}

	public function test_from()
	{
		$target = new SampleTarget;
		$event = BeforePracticeEvent::from([ 'target' => $target ]);

		$this->assertInstanceOf(BeforePracticeEvent::class, $event);
	}

	public function test_stop()
	{
		$type = 'event-' . uniqid();

		$this->events->attach($type, function(Event $event) {

			$this->fail("Should not be invoked.");

		});

		$this->events->attach($type, function(Event $event) {

			$event->stop();

		});

		$event = new Event(null, $type);

		$this->assertTrue($event->stopped);
	}

	public function test_used_by()
	{
		$type = 'event-' . uniqid();

		$hook1 = function(Event $event) { };
		$hook2 = function(Event $event) { };

		$this->events->attach($type, $hook1);
		$this->events->attach($type, $hook2);

		$event = new Event(null, $type);

		$this->assertSame($hook2, $event->used_by[0][0]);
		$this->assertSame($hook1, $event->used_by[1][0]);
		$this->assertEquals(2, $event->used);
	}

	public function test_used_by_with_exception()
	{
		$type = 'event-' . uniqid();
		$exception = new \Exception;

		$hook = function (Event $event) use ($exception) {

			throw $exception;

		};

		$this->events->attach($type, $hook);

		$event = Event::from([ 'target' => null, 'type' => $type ]);

		try {
			$event->fire();
		} catch (\Exception $e) {
			$this->assertSame($exception, $e);
			$this->assertEquals(1, $event->used);
			$this->assertSame($hook, $event->used_by[0][0]);
			return;
		}

		$this->fail("Expected Exception");
	}

	public function test_target()
	{
		$target = new SampleTarget;
		$event = new Event($target, uniqid());

		$this->assertSame($target, $event->target);
	}

	/**
	 * The big test, with reflection and chains.
	 */
	public function testEventHooks()
	{
		/*
		 * The A::validate() method would return false if the following hook wasn't called.
		 */
		$this->events->attach(function(ValidateEvent $event, A $target) {

			$event->valid = true;
		});

		/*
		 * We add "three" to the values of A instances before they are processed.
		 */
		$this->events->attach(function(BeforeProcessEvent $event, A $target) {

			$event->values['three'] = 3;
		});

		/*
		 * This hook is called before any hook set on the A class, because we want "four" to be
		 * after "three", which is added by the hook above, we use the _chain_ feature of the event.
		 *
		 * Hooks pushed by the chain() method are executed after the even chain was processed.
		 */
		$this->events->attach(function(BeforeProcessEvent $event, B $target) {

			$event->chain(function($event) {

				$event->values['four'] = 4;
			});
		});

		/*
		 * 10 is added to all processed values of A instances.
		 */
		$this->events->attach(function(ProcessEvent $event, A $target) {

			array_walk($event->values, function(&$v) {

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
		$this->events->attach(function(ProcessEvent $event, B $target) {

			array_walk($event->values, function(&$v) {

				$v *= 10;
			});

			$event->stop();
		});

		$initial_array = [ 'one' => 1, 'two' => 2 ];

		$a = new A;
		$b = new B;

		$a_processed = $a($initial_array);
		$b_processed = $b($initial_array);

		$this->assertEquals([ 'one' => 11, 'two' => 12, 'three' => 13 ], $a_processed);
		$this->assertEquals('one,two,three', implode(',', array_keys($a_processed)));

		$this->assertEquals([ 'one' => 10, 'two' => 20, 'three' => 30, 'four' => 40, 'five' => 50 ], $b_processed);
		$this->assertEquals('one,two,three,four,five', implode(',', array_keys($b_processed)));
	}


	/**
	 * @dataProvider provide_test_reserved
	 */
	public function test_reserved(array $payload)
	{
		$type = "event" . uniqid();

		$this->events->attach($type, function() { });

		$this->expectException(PropertyIsReserved::class);
		new Event(null, $type, $payload);
	}

	public function provide_test_reserved()
	{
		return [

			[ [ 'chain' => 123 ] ],
			[ [ 'stopped' => 123 ] ],
			[ [ 'target' => 123 ] ],
			[ [ 'used' => 123 ] ],
			[ [ 'used_by' => 123 ] ],
			[ [ 'ok' => "ok", 'chain' => 123 ] ],
			[ [ 'chain' => 123, 'ok' => "ok" ] ],
			[ [ 'ok1' => "ok", 'chain' => 123, 'ok2' => "ok" ] ],

		];
	}
}

namespace Test\ICanBoogie\EventTest;

use ICanBoogie\Event;

class A
{
	public function __invoke(array $values)
	{
		if (!$this->validate($values))
		{
			throw new \Exception("Values validation failed.");
		}

		new BeforeProcessEvent($this, [ 'values' => &$values ]);

		return $this->process($values);
	}

	protected function validate(array $values)
	{
		$valid = false;

		new ValidateEvent($this, [ 'values' => $values, 'valid' => &$valid ]);

		return $valid;
	}

	protected function process(array $values)
	{
		new ProcessEvent($this, [ 'values' => &$values ]);

		return $values;
	}
}

class B extends A
{
	protected function process(array $values)
	{
		return parent::process($values + [ 'five' => 5 ]);
	}
}

/**
 * Event class for the `Test\A::validate` event.
 */
class ValidateEvent extends Event
{
	public $values;

	public $valid;

	public function __construct(A $target, array $payload)
	{
		parent::__construct($target, 'validate', $payload);
	}
}

/**
 * Event class for the `Test\A::process:before` event.
 */
class BeforeProcessEvent extends Event
{
	public $values;

	public function __construct(A $target, array $payload)
	{
		parent::__construct($target, 'process:before', $payload);
	}
}

/**
 * Event class for the `Test\A::process` event.
 */
class ProcessEvent extends Event
{
	public $values;

	public function __construct(A $target, array $payload = [])
	{
		parent::__construct($target, 'process', $payload);
	}
}
