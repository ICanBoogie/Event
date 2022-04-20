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

use ICanBoogie\EventHookReflection;
use LogicException;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\EventTest\CallableInstance;
use Test\ICanBoogie\EventTest\Hooks;
use Test\ICanBoogie\SampleTarget\BeforePracticeEvent;
use Test\ICanBoogie\SampleTarget\PracticeEvent;

final class EventHookRefectionTest extends TestCase
{
	/**
	 * @dataProvider provide_event_hooks
	 */
	public function test_valid(mixed $hook)
	{
		EventHookReflection::assert_valid($hook);

		$this->assertTrue(true);
	}

	public function test_from(): void
	{
		$hook = function (PracticeEvent $event, SampleTarget $target) {
		};

		$reflection = EventHookReflection::from($hook);
		$this->assertSame($reflection, EventHookReflection::from($hook));
	}

	/**
	 * @dataProvider provide_event_hooks
	 */
	public function test_type(mixed $hook): void
	{
		$this->assertEquals(
			BeforePracticeEvent::for(SampleTarget::class),
			EventHookReflection::from($hook)->type
		);
	}

	public function provide_event_hooks(): array
	{
		return [

			[ before_target_practice(...) ],
			[ Hooks::class . '::before_target_practice' ],
			[ [ Hooks::class, 'before_target_practice' ] ],
			[
				function (BeforePracticeEvent $event, SampleTarget $target) {
				}
			],
			[ new CallableInstance ]

		];
	}

	public function test_invalid_parameters_number(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessageMatches("/Invalid number of parameters/");
		$this->assertNull(EventHookReflection::from(function () {
		}));
	}

	public function test_invalid_parameters(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage("The parameter `a` must be an instance of `ICanBoogie\\Event`.");
		$this->assertNull(EventHookReflection::from(function ($a, SampleTarget $b) {
		}));
	}
}

function before_target_practice(BeforePracticeEvent $event, SampleTarget $target)
{
}
