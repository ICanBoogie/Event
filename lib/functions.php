<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

/**
 * Returns the current event collection.
 *
 * **Note:** A provider for a new event collection is defined is none was defined yet.
 *
 * @return EventCollection
 */
function get_events()
{
	$provider = EventCollectionProvider::defined();

	if (!$provider)
	{
		$provider = function() {

			static $events;

			return $events ?: $events = new EventCollection;

		};

		EventCollectionProvider::define($provider);
	}

	return $provider();
}

namespace ICanBoogie\Event;

use function is_object;

/**
 * @param string $type An unqualified event type e.g. "recover"
 * @param object|class-string $target
 *
 * @return string
 *     An qualified event type made of the target class and the unqualified event type.
 *     e.g. "Exception::recover"
 */
function qualify_type(string $type, object|string $target): string
{
	if (is_object($target)) {
		$target = $target::class;
	}

	return "$target::$type";
}
