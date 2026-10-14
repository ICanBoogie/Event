<?php

namespace ICanBoogie\Event;

/**
 * Immutable set of event listeners, created with {@see ConfigBuilder}.
 *
 * It can be given to the `EventCollection` constructor or to `attach_many()`. Because it implements
 * `__set_state()`, it can be exported with `var_export()`, which allows it to be cached as PHP
 * code.
 */
final readonly class Config
{
    /**
     * @param array{ 'listeners': array<non-empty-string, callable[]> } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self($an_array['listeners']);
    }

    /**
     * @param array<non-empty-string, callable[]> $listeners
     *     The listeners, grouped by event type. The key is either an event class, for events
     *     without sender, or a qualified event type as returned by {@see \ICanBoogie\Event::for()},
     *     e.g. "Exception::recover".
     */
    public function __construct(
        public array $listeners
    ) {
    }
}
