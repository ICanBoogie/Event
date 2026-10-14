<?php

namespace Test\ICanBoogie;

use ICanBoogie\ListenerReflection;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Sample\SampleCallableWithoutSender;
use Test\ICanBoogie\Sample\SampleCallableWithSender;
use Test\ICanBoogie\Sample\SampleEvent;
use Test\ICanBoogie\Sample\SampleListeners;
use Test\ICanBoogie\Sample\SampleSender;
use Test\ICanBoogie\Sample\SampleSender\ActionEvent;
use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

final class EventListenerReflectionTest extends TestCase
{
    public function test_from_object_method(): void
    {
        $listeners = new class () {
            public function on_action(ActionEvent $event, SampleSender $sender): void
            {
            }
        };

        $reflection = ListenerReflection::from([ $listeners, 'on_action' ]);

        $this->assertSame(SampleSender::class . '::' . ActionEvent::class, $reflection->type);
        $this->assertSame($reflection, ListenerReflection::from([ $listeners, 'on_action' ]));
    }

    #[DataProvider('provide_event_listeners_with_sender')]
    public function test_valid(mixed $listener): void
    {
        ListenerReflection::assert_valid($listener);

        $this->assertTrue(true); // @phpstan-ignore-line
    }

    public function test_from(): void
    {
        $listener = function (ActionEvent $event, SampleSender $sender) {
        };

        $reflection = ListenerReflection::from($listener);
        $this->assertSame($reflection, ListenerReflection::from($listener));
    }

    #[DataProvider('provide_event_listeners_with_sender')]
    public function test_type_with_sender(callable $listener): void
    {
        $this->assertEquals(
            BeforeActionEvent::for(SampleSender::class),
            ListenerReflection::from($listener)->type,
        );
    }

    public static function provide_event_listeners_with_sender(): array
    {
        return [

            [ listener_with_sender(...) ],
            [ SampleListeners::class . '::with_sender' ],
            [ [ SampleListeners::class, 'with_sender' ] ],
            [
                function (BeforeActionEvent $event, SampleSender $sender) {
                },
            ],
            [ new SampleCallableWithSender() ],

        ];
    }

    #[DataProvider('provide_event_listeners_without_sender')]
    public function test_type_without_sender(callable $listener): void
    {
        $this->assertEquals(
            SampleEvent::class,
            ListenerReflection::from($listener)->type,
        );
    }

    public static function provide_event_listeners_without_sender(): array
    {
        return [

            [ listener_without_sender(...) ],
            [ SampleListeners::class . '::without_sender' ],
            [ [ SampleListeners::class, 'without_sender' ] ],
            [
                function (SampleEvent $event) {
                },
            ],
            [ new SampleCallableWithoutSender() ],

        ];
    }

    public function test_invalid_parameters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The parameter `a` must be an instance of ICanBoogie\\Event");
        ListenerReflection::from(function ($a, SampleSender $b) {
        });
    }

    public function test_too_few_parameters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Expecting at least 1 parameter got none.");
        ListenerReflection::from(function () {
        });
    }

    public function test_too_few_many_parameters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Expecting at most 2 parameters got 3.");
        ListenerReflection::from(function ($a, $b, $c) {
        });
    }
}

function listener_with_sender(BeforeActionEvent $event, SampleSender $sender): void
{
}

function listener_without_sender(SampleEvent $event): void
{
}
