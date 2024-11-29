<?php

namespace ICanBoogie\Event;

use Closure;
use ICanBoogie\EventCollection;

/**
 * Used to detach an event.
 *
 * ```php
 * <?php
 *
 * use ICanBoogie\HTTP\Dispatcher;
 *
 * $detach = $events->attach(function(Dispatcher\CollectEvent $event, Dispatcher $sender) {
 *
 *     // …
 *
 * });
 *
 * // …
 *
 * $detach();
 * ```
 *
 * @internal
 */
final readonly class Detach
{
    public function __construct(
        private EventCollection $events,
        private string $type,
        private Closure $hook
    ) {
    }

    /**
     * Detaches the event hook from the events.
     */
    public function __invoke(): void
    {
        $this->events->detach($this->type, $this->hook);
    }
}
