<?php

namespace ICanBoogie;

use ICanBoogie\EventTest\CallableInstance;
use ICanBoogie\EventTest\Hooks;
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

	/**
	 * @dataProvider provide_test_should_resolve_event_type_from_hook
	 *
	 * @param mixed $hook
	 */
	public function test_should_resolve_event_type_from_hook($hook)
	{
		$this->assertEquals('ICanBoogie\EventTest\Target::practice:before', $this->events->attach($hook)->type);
	}

	public function provide_test_should_resolve_event_type_from_hook()
	{
		return [

			[ 'ICanBoogie\EventTest\before_target_practice' ],
			[ Hooks::class . '::before_target_practice' ],
			[ [ Hooks::class, 'before_target_practice' ] ],
			[ function(Target\BeforePracticeEvent $event, Target $target) { } ],
			[ new CallableInstance ]

		];
	}

	/**
	 * @depends test_should_resolve_event_type_from_hook
	 */
	public function test_detach_hook()
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
	 * @depends test_detach_hook
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
}
