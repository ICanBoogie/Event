<?php

namespace ICanBoogie\EventProfiler;

/**
 * @internal
 */
final readonly class UnusedRecord
{
    /**
     * @param float $time
     *     The time when the event was emitted, in microsecond.
     * @param string $type
     *     The type of the event.
     */
    public function __construct(
        public float $time,
        public string $type,
    ) {
    }
}
