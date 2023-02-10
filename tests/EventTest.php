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

use Error;
use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Sample\Processor;
use Test\ICanBoogie\Sample\Processor\BeforeProcessEvent;
use Test\ICanBoogie\Sample\Processor\ProcessEvent;
use Test\ICanBoogie\Sample\Processor\ValidateEvent;
use Test\ICanBoogie\Sample\ProcessorExtension;
use Test\ICanBoogie\Sample\SampleEvent;
use Test\ICanBoogie\Sample\SampleSender;

use function ICanBoogie\emit;

final class EventTest extends TestCase
{
    private EventCollection $events;

    protected function setUp(): void
    {
        $this->events = new EventCollection();

        EventCollectionProvider::define(fn() => $this->events);
    }

    public function test_uninitialized_sender(): void
    {
        $event = new SampleEvent();

        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches("/sender must not be accessed before initialization/");
        $this->assertNull($event->sender);
    }

    public function test_for(): void
    {
        $this->assertEquals(
            "Test\ICanBoogie\Sample\SampleSender::Test\ICanBoogie\Sample\Processor\BeforeProcessEvent",
            BeforeProcessEvent::for(SampleSender::class)
        );
    }

    public function test_stop(): void
    {
        $n = 0;

        $this->events->attach(SampleEvent::class, function (SampleEvent $event) use (&$n) {
            $n++;
        });

        $this->events->attach(SampleEvent::class, function (SampleEvent $event) {
            $event->stop();
        });

        $event = emit(new SampleEvent());

        $this->assertTrue($event->stopped);
        $this->assertEquals(0, $n);
    }

    public function test_sender(): void
    {
        $sender = new SampleSender();
        $event = new SampleEvent($sender);

        $this->assertSame($sender, $event->sender);
    }

    /**
     * The big test, with reflection and chains.
     */
    public function testEventHooks(): void
    {
        /*
         * The A::validate() method would return false if the following hook wasn't called.
         */
        $this->events->attach(function (ValidateEvent $event, Processor $sender) {
            $event->valid = true;
        });

        /*
         * We add "three" to the values of A instances before they are processed.
         */
        $this->events->attach(function (BeforeProcessEvent $event, Processor $sender) {
            $event->values['three'] = 3;
        });

        /*
         * This hook is called before any hook set on the A class, because we want "four" to be
         * after "three", which is added by the hook above, we use the _chain_ feature of the event.
         *
         * Hooks pushed by the chain() method are executed after the even chain was processed.
         */
        $this->events->attach(function (BeforeProcessEvent $event, ProcessorExtension $sender) {
            $event->chain(function ($event) {
                $event->values['four'] = 4;
            });
        });

        /*
         * 10 is added to all processed values of A instances.
         */
        $this->events->attach(function (ProcessEvent $event, Processor $sender) {
            array_walk($event->values, function (&$v) {
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
        $this->events->attach(function (ProcessEvent $event, ProcessorExtension $sender) {
            array_walk($event->values, function (&$v) {
                $v *= 10;
            });

            $event->stop();
        });

        $initial_array = [ 'one' => 1, 'two' => 2 ];

        $a = new Processor();
        $b = new ProcessorExtension();

        $a_processed = $a($initial_array);
        $b_processed = $b($initial_array);

        $this->assertEquals([ 'one' => 11, 'two' => 12, 'three' => 13 ], $a_processed);
        $this->assertEquals('one,two,three', implode(',', array_keys($a_processed)));

        $this->assertEquals([ 'one' => 10, 'two' => 20, 'three' => 30, 'four' => 40, 'five' => 50 ], $b_processed);
        $this->assertEquals('one,two,three,four,five', implode(',', array_keys($b_processed)));
    }
}
