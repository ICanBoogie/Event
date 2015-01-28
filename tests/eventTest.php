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

use ICanBoogie\HTTP\Dispatcher;

use ICanBoogie\EventTest\A;
use ICanBoogie\EventTest\Attach;
use ICanBoogie\EventTest\B;
use ICanBoogie\EventTest\BeforeProcessEvent;
use ICanBoogie\EventTest\ProcessEvent;
use ICanBoogie\EventTest\ValidateEvent;

class EventTest extends \PHPUnit_Framework_TestCase
{
	static private $events;

	static public function setUpBeforeClass()
	{
		self::$events = $events = new Events;

		Events::patch('get', function() use($events) { return $events; });
	}

	public function testAttachFunction()
	{
		$eh = self::$events->attach('ICanBoogie\EventTest\hook_callback');

		$this->assertEquals('ICanBoogie\HTTP\Dispatcher::dispatch:before', $eh->type);
	}

	public function testAttachMethod()
	{
		$eh = self::$events->attach('ICanBoogie\EventTest\Attach::hook_callback');

		$this->assertEquals('ICanBoogie\HTTP\Dispatcher::dispatch:before', $eh->type);
	}

	public function testAttachClosure()
	{
		$eh = self::$events->attach(function(Dispatcher\BeforeDispatchEvent $event, Dispatcher $target) { });

		$this->assertEquals('ICanBoogie\HTTP\Dispatcher::dispatch:before', $eh->type);
	}

	/**
	 * Are event hooks are correctly detached ?
	 */
	public function testDetach()
	{
		$done = null;

		$hook = function(Event $event) use (&$done)
		{
			$done = true;
		};

		self::$events->attach('tmp', $hook);

		new Event(null, 'tmp');

		$this->assertTrue($done);

		$done = null;

		self::$events->detach('tmp', $hook);

		new Event(null, 'tmp');

		$this->assertNull($done);
	}

	/**
	 * Are event hooks are correctly detached ?
	 */
	public function testDetachUsingInterface()
	{
		$done = null;

		$he = self::$events->attach('tmp', function(Event $event) use (&$done)
		{
			$done = true;
		});

		new Event(null, 'tmp');

		$this->assertTrue($done);

		$done = null;

		$he->detach();

		new Event(null, 'tmp');

		$this->assertNull($done);
	}

	/**
	 * Are event hooks attached to classes are correctly detached ?
	 */
	public function testDetachTypedEvent()
	{
		$a = new \ICanBoogie\EventTest\A;

		$done = null;

		$hook = function(Event $event) use (&$done)
		{
			$done = true;
		};

		self::$events->attach(get_class($a) . '::tmp', $hook);

		new Event($a, 'tmp');

		$this->assertTrue($done);

		$done = null;

		self::$events->detach(get_class($a) . '::tmp', $hook);

		new Event($a, 'tmp');

		$this->assertNull($done);
	}

	/**
	 * Are event hooks attached to classes are correctly detached ?
	 */
	public function testDetachTypedEventUsingInterface()
	{
		$a = new \ICanBoogie\EventTest\A;

		$done = null;

		$he = self::$events->attach(get_class($a) . '::tmp', function(Event $event) use (&$done)
		{
			$done = true;
		});

		new Event($a, 'tmp');

		$this->assertTrue($done);

		$done = null;

		$he->detach();

		new Event($a, 'tmp');

		$this->assertNull($done);
	}

	/**
	 * The big test, with reflection and chains.
	 */
	public function testEventHooks()
	{
		/*
		 * The A::validate() method would return false if the following hook wasn't called.
		 */
		self::$events->attach(function(ValidateEvent $event, A $target) {

			$event->valid = true;
		});

		/*
		 * We add "three" to the values of A instances before they are processed.
		 */
		self::$events->attach(function(BeforeProcessEvent $event, A $target) {

			$event->values['three'] = 3;
		});

		/*
		 * This hook is called before any hook set on the A class, because we want "four" to be
		 * after "three", which is added by the hook above, we use the _chain_ feature of the event.
		 *
		 * Hooks pushed by the chain() method are executed after the even chain was processed.
		 */
		self::$events->attach(function(BeforeProcessEvent $event, B $target) {

			$event->chain(function($event) {

				$event->values['four'] = 4;
			});
		});

		/*
		 * 10 is added to all processed values of A instances.
		 */
		self::$events->attach(function(ProcessEvent $event, A $target) {

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
		self::$events->attach(function(ProcessEvent $event, B $target) {

			array_walk($event->values, function(&$v) {

				$v *= 10;
			});

			$event->stop();
		});

		$initial_array = array('one' => 1, 'two' => 2);

		$a = new A;
		$b = new B;

		$a_processed = $a($initial_array);
		$b_processed = $b($initial_array);

		$this->assertEquals(array('one' => 11, 'two' => 12, 'three' => 13), $a_processed);
		$this->assertEquals('one,two,three', implode(',', array_keys($a_processed)));

		$this->assertEquals(array('one' => 10, 'two' => 20, 'three' => 30, 'four' => 40, 'five' => 50), $b_processed);
		$this->assertEquals('one,two,three,four,five', implode(',', array_keys($b_processed)));
	}

	public function test_once()
	{
		$n = 0;
		$m = 0;

		$once = function() use(&$n) {

			$n++;

		};

		self::$events->once('once', $once);

		$eh = self::$events->attach('once', function() use(&$m) {

			$m++;

		});

		new Event(null, 'once');
		new Event(null, 'once');
		new Event(null, 'once');

		$this->assertEquals(1, $n);
		$this->assertEquals(3, $m);

		$eh->detach();

		self::$events->once('once', $once);

		new Event(null, 'once');
		new Event(null, 'once');
		new Event(null, 'once');

		$this->assertEquals(2, $n);

		self::$events->attach('once', $once);

		new Event(null, 'once');
		new Event(null, 'once');
		new Event(null, 'once');

		$this->assertEquals(5, $n);
	}

	/**
	 * @dataProvider provide_test_reserved
	 * @expectedException \ICanBoogie\PropertyIsReserved
	 */
	public function test_reserved(array $payload)
	{
		$type = "event" . uniqid();

		self::$events->attach($type, function() { });

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

namespace ICanBoogie\EventTest;

use ICanBoogie\Event;
use ICanBoogie\HTTP\Dispatcher;

class A
{
	public function __invoke(array $values)
	{
		if (!$this->validate($values))
		{
			throw new \Exception("Values validation failed.");
		}

		new BeforeProcessEvent($this, array('values' => &$values));

		return $this->process($values);
	}

	protected function validate(array $values)
	{
		$valid = false;

		new ValidateEvent($this, array('values' => $values, 'valid' => &$valid));

		return $valid;
	}

	protected function process(array $values)
	{
		new ProcessEvent($this, array('values' => &$values));

		return $values;
	}
}

class B extends A
{
	protected function process(array $values)
	{
		return parent::process($values + array('five' => 5));
	}
}

function hook_callback(Dispatcher\BeforeDispatchEvent $event, Dispatcher $target)
{

}

class Attach
{
	static public function hook_callback(Dispatcher\BeforeDispatchEvent $event, Dispatcher $target)
	{

	}
}

/**
 * Event class for the `Test\A::validate` event.
 */
class ValidateEvent extends Event
{
	public $values;

	public $valid;

	public function __construct(A $target, array $properties)
	{
		parent::__construct($target, 'validate', $properties);
	}
}

/**
 * Event class for the `Test\A::process:before` event.
 */
class BeforeProcessEvent extends Event
{
	public $values;

	public function __construct(A $target, array $properties)
	{
		parent::__construct($target, 'process:before', $properties);
	}
}

/**
 * Event class for the `Test\A::process` event.
 */
class ProcessEvent extends Event
{
	public $values;

	public function __construct(A $target, array $properties)
	{
		parent::__construct($target, 'process', $properties);
	}
}
