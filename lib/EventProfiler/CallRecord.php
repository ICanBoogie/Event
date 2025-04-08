<?php

namespace ICanBoogie\EventProfiler;

/**
 * @internal
 */
final readonly class CallRecord
{
    /**
     * @param float $time
     *     The time when the event was emitted, in microsecond.
     * @param float $duration
     *     The duration of the hook execution.
     * @param string $type
     *     The type of the event.
     * @param callable $hook
     *     The callable that handled the event.
     */
    public function __construct(
        public float $time,
        public float $duration,
        public string $type,
        public mixed $hook,
    ) {
    }
}
