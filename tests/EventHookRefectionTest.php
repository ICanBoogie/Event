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
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\EventTest\CallableInstance;
use Test\ICanBoogie\EventTest\Hooks;
use Test\ICanBoogie\SampleTarget\BeforePracticeEvent;
use Test\ICanBoogie\SampleTarget\PracticeEvent;

use function ICanBoogie\Event\qualify_type;

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

	public function test_from()
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
			qualify_type(BeforePracticeEvent::TYPE, SampleTarget::class),
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
		$reflection = EventHookReflection::from(function () {
		});
		$this->expectException(\LogicException::class);
		$reflection->type;
	}

	public function test_invalid_parameters(): void
	{
		$reflection = EventHookReflection::from(function ($a, SampleTarget $b) {
		});
		$this->expectException(\LogicException::class);
		$reflection->type;
	}
}

function before_target_practice(BeforePracticeEvent $event, SampleTarget $target)
{

}
