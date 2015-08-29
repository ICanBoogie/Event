<?php

namespace ICanBoogie;

use ICanBoogie\EventTest\CallableInstance;
use ICanBoogie\EventTest\Hooks;
use ICanBoogie\EventTest\Target;

class EventHookRefectionTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider provide_event_hooks
	 *
	 * @param mixed $hook
	 */
	public function test_valid($hook)
	{
		EventHookReflection::assert_valid($hook);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function test_invalid()
	{
		EventHookReflection::assert_valid(123);
	}

	public function test_from()
	{
		$hook = function(Target\PracticeEvent $event, Target $target) {};

		$reflection = EventHookReflection::from($hook);
		$this->assertSame($reflection, EventHookReflection::from($hook));
	}

	/**
	 * @dataProvider provide_event_hooks
	 *
	 * @param mixed $hook
	 */
	public function test_type($hook)
	{
		$this->assertEquals('ICanBoogie\EventTest\Target::practice:before', EventHookReflection::from($hook)->type);
	}

	public function provide_event_hooks()
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
	 * @expectedException \LogicException
	 */
	public function test_invalid_parameters_number()
	{
		$reflection = EventHookReflection::from(function() {});
		$reflection->type;
	}

	/**
	 * @expectedException \LogicException
	 */
	public function test_invalid_parameters()
	{
		$reflection = EventHookReflection::from(function($a, Target $b) {});
		$reflection->type;
	}
}
