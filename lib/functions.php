<?php

namespace ICanBoogie;

/**
 * Returns the current event collection.
 *
 * **Note**: A provider for a new event collection is defined is none was defined yet.
 */
function get_events(): EventCollection
{
    $provider = EventCollectionProvider::defined();

    if (!$provider) {
        $provider = function () {
            static $events;

            return $events ??= new EventCollection();
        };

        EventCollectionProvider::define($provider);
    }

    return $provider();
}

/**
 * @template T of Event
 *
 * @param T $event
 *
 * @return T
 */
function emit(Event $event): Event
{
    return get_events()->emit($event);
}
