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
use Test\ICanBoogie\Sample\SampleCallableWithoutTarget;
use Test\ICanBoogie\Sample\SampleCallableWithTarget;
use Test\ICanBoogie\Sample\SampleEvent;
use Test\ICanBoogie\Sample\SampleHooks;
use Test\ICanBoogie\Sample\SampleTarget;
use Test\ICanBoogie\Sample\SampleTarget\BeforeActionEvent;
use Test\ICanBoogie\Sample\SampleTarget\ActionEvent;

final class EventHookRefectionTest extends TestCase
{
	/**
	 * @dataProvider provide_event_hooks_with_target
	 */
	public function test_valid(mixed $hook)
	{
		EventHookReflection::assert_valid($hook);

		$this->assertTrue(true);
	}

	public function test_from(): void
	{
		$hook = function (ActionEvent $event, SampleTarget $target) {
		};

		$reflection = EventHookReflection::from($hook);
		$this->assertSame($reflection, EventHookReflection::from($hook));
	}

	/**
	 * @dataProvider provide_event_hooks_with_target
	 */
	public function test_type_with_target(mixed $hook): void
	{
		$this->assertEquals(
			BeforeActionEvent::for(SampleTarget::class),
			EventHookReflection::from($hook)->type
		);
	}

	public function provide_event_hooks_with_target(): array
	{
		return [

			[ hook_with_target(...) ],
			[ SampleHooks::class . '::with_target' ],
			[ [ SampleHooks::class, 'with_target' ] ],
			[
				function (BeforeActionEvent $event, SampleTarget $target) {
				}
			],
			[ new SampleCallableWithTarget() ]

		];
	}

	/**
	 * @dataProvider provide_event_hooks_without_target
	 */
	public function test_type_without_target(mixed $hook): void
	{
		$this->assertEquals(
			SampleEvent::class,
			EventHookReflection::from($hook)->type
		);
	}

	public function provide_event_hooks_without_target(): array
	{
		return [

			[ hook_without_target(...) ],
			[ SampleHooks::class . '::without_target' ],
			[ [ SampleHooks::class, 'without_target' ] ],
			[
				function (SampleEvent $event) {
				}
			],
			[ new SampleCallableWithoutTarget() ]

		];
	}

	public function test_invalid_parameters(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage("The parameter `a` must be an instance of `ICanBoogie\\Event`.");
		$this->assertNull(EventHookReflection::from(function ($a, SampleTarget $b) {
		}));
	}

	public function test_too_few_parameters(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage("Expecting at least 1 parameter got none.");
		$this->assertNull(EventHookReflection::from(function () {
		}));
	}

	public function test_too_few_many_parameters(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage("Expecting at most 2 parameters got 3.");
		$this->assertNull(EventHookReflection::from(function ($a, $b, $c) {
		}));
	}
}

function hook_with_target(BeforeActionEvent $event, SampleTarget $target)
{
}

function hook_without_target(SampleEvent $event)
{
}
