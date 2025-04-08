<?php

namespace ICanBoogie;

use ArrayIterator;
use ICanBoogie\EventProfiler\CallRecord;
use ICanBoogie\EventProfiler\UnusedRecord;

use function microtime;

/**
 * Profiling information about events.
 */
final class EventProfiler
{
    /**
     * @var UnusedRecord[]
     */
    private static array $unused = [];

    /**
     * Adds an unused event type.
     */
    public static function add_unused(string $type): void
    {
        self::$unused[] = new UnusedRecord(microtime(true), $type);
    }

    public static function reset_unused(): void
    {
        self::$unused = [];
    }

    /**
     * @return iterable<UnusedRecord>
     */
    public static function iter_unused(): iterable
    {
        return new ArrayIterator(self::$unused);
    }

    /**
     * Event hooks calls.
     *
     * @var CallRecord[]
     */
    private static array $calls = [];

    /**
     * Adds an event hook call.
     */
    public static function add_call(string $type, callable $hook, float $started_at): void
    {
        self::$calls[] = new CallRecord($started_at, microtime(true) - $started_at, $type, $hook);
    }

    public static function reset_calls(): void
    {
        self::$calls = [];
    }

    /**
     * @return iterable<CallRecord>
     */
    public static function iter_calls(): iterable
    {
        return new ArrayIterator(self::$calls);
    }
}
