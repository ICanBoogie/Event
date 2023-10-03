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
use Test\ICanBoogie\Sample\SampleCallableWithoutSender;
use Test\ICanBoogie\Sample\SampleCallableWithSender;
use Test\ICanBoogie\Sample\SampleEvent;
use Test\ICanBoogie\Sample\SampleHooks;
use Test\ICanBoogie\Sample\SampleSender;
use Test\ICanBoogie\Sample\SampleSender\ActionEvent;
use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

final class EventHookRefectionTest extends TestCase
{
    /**
     * @dataProvider provide_event_hooks_with_sender
     */
    public function test_valid(mixed $hook)
    {
        EventHookReflection::assert_valid($hook);

        $this->assertTrue(true);
    }

    public function test_from(): void
    {
        $hook = function (ActionEvent $event, SampleSender $sender) {
        };

        $reflection = EventHookReflection::from($hook);
        $this->assertSame($reflection, EventHookReflection::from($hook));
    }

    /**
     * @dataProvider provide_event_hooks_with_sender
     */
    public function test_type_with_sender(mixed $hook): void
    {
        $this->assertEquals(
            BeforeActionEvent::for(SampleSender::class),
            EventHookReflection::from($hook)->type
        );
    }

    public static function provide_event_hooks_with_sender(): array
    {
        return [

            [ hook_with_sender(...) ],
            [ SampleHooks::class . '::with_sender' ],
            [ [ SampleHooks::class, 'with_sender' ] ],
            [
                function (BeforeActionEvent $event, SampleSender $sender) {
                }
            ],
            [ new SampleCallableWithSender() ]

        ];
    }

    /**
     * @dataProvider provide_event_hooks_without_sender
     */
    public function test_type_without_sender(mixed $hook): void
    {
        $this->assertEquals(
            SampleEvent::class,
            EventHookReflection::from($hook)->type
        );
    }

    public static function provide_event_hooks_without_sender(): array
    {
        return [

            [ hook_without_sender(...) ],
            [ SampleHooks::class . '::without_sender' ],
            [ [ SampleHooks::class, 'without_sender' ] ],
            [
                function (SampleEvent $event) {
                }
            ],
            [ new SampleCallableWithoutSender() ]

        ];
    }

    public function test_invalid_parameters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The parameter `a` must be an instance of `ICanBoogie\\Event`.");
        $this->assertNull(
            EventHookReflection::from(function ($a, SampleSender $b) {
            })
        );
    }

    public function test_too_few_parameters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Expecting at least 1 parameter got none.");
        $this->assertNull(
            EventHookReflection::from(function () {
            })
        );
    }

    public function test_too_few_many_parameters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Expecting at most 2 parameters got 3.");
        $this->assertNull(
            EventHookReflection::from(function ($a, $b, $c) {
            })
        );
    }
}

function hook_with_sender(BeforeActionEvent $event, SampleSender $sender)
{
}

function hook_without_sender(SampleEvent $event)
{
}
