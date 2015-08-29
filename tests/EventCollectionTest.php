<?php

namespace ICanBoogie;

use ICanBoogie\EventTest\Target;

class EventCollectionTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var EventCollection
	 */
	private $events;

	public function setUp()
	{
		$this->events = $events = new EventCollection;

		EventCollection::set_instance_provider(function () use ($events) { return $events; });
	}

	public function test_get()
	{
		$this->assertSame($this->events, EventCollection::get());
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

	/**
	 * @expectedException \LogicException
	 */
	public function test_detach_unattached_hook()
	{
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

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function test_attach_to_should_throw_exception_when_target_is_not_an_object()
	{
		$this->events->attach_to(uniqid(), function() {});
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
}
