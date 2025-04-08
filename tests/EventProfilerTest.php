<?php

namespace Test\ICanBoogie;

use ICanBoogie\EventProfiler;
use ICanBoogie\EventProfiler\CallRecord;
use ICanBoogie\EventProfiler\UnusedRecord;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Sample\SampleSender;
use Test\ICanBoogie\Sample\SampleSender\ActionEvent;
use Test\ICanBoogie\Sample\SampleSender\BeforeActionEvent;

final class EventProfilerTest extends TestCase
{
    public function test_iter_unused(): void
    {
        $now = microtime(true);
        EventProfiler::reset_unused();
        EventProfiler::add_unused($type1 = BeforeActionEvent::for(SampleSender::class));
        EventProfiler::add_unused($type2 = ActionEvent::for(SampleSender::class));

        /** @var UnusedRecord[] $actual */
        $actual = iterator_to_array(EventProfiler::iter_unused());

        $this->assertCount(2, $actual);
        $this->assertEquals($type1, $actual[0]->type);
        $this->assertGreaterThanOrEqual($now, $actual[0]->time);
        $this->assertEquals($type2, $actual[1]->type);
        $this->assertGreaterThanOrEqual($now, $actual[1]->time);
    }

    public function test_iter_calls(): void
    {
        $now = microtime(true);
        EventProfiler::reset_calls();
        usleep( 200);
        EventProfiler::add_call(
            $type1 = BeforeActionEvent::for(SampleSender::class),
            $hook1 = fn() => null,
            $now,
        );
        usleep( 400);
        EventProfiler::add_call(
            $type2 = ActionEvent::for(SampleSender::class),
            $hook2 = fn() => null,
            $now,
        );

        /** @var CallRecord[] $actual */
        $actual = iterator_to_array(EventProfiler::iter_calls());

        $this->assertCount(2, $actual);
        $this->assertEquals($type1, $actual[0]->type);
        $this->assertEquals($now, $actual[0]->time);
        $this->assertGreaterThanOrEqual(0, $actual[0]->duration);
        $this->assertEquals($hook1, $actual[0]->hook);
        $this->assertEquals($type2, $actual[1]->type);
        $this->assertEquals($now, $actual[1]->time);
        $this->assertEquals($hook2, $actual[1]->hook);
        $this->assertGreaterThanOrEqual(0, $actual[1]->duration);
        $this->assertGreaterThanOrEqual($actual[0]->duration, $actual[1]->duration);
    }
}
