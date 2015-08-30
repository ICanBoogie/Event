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
 * Provides an {@link EventCollection} instance.
 */
class EventCollectionProvider
{
	/**
	 * @var callable {@link EventCollection} provider
	 */
	static private $provider;

	/**
	 * Defines the {@link EventCollection} provider.
	 *
	 * @param callable $provider
	 *
	 * @return callable The previous provider, or `null` if none was defined before.
	 */
	static public function using(callable $provider)
	{
		$previous = self::$provider;

		self::$provider = $provider;

		return $previous;
	}

	/**
	 * Returns a {@link EventCollection} instance using the provider.
	 *
	 * @return EventCollection
	 */
	static public function provide()
	{
		$provider = self::$provider;

		if (!$provider)
		{
			throw new \LogicException("No provider is defined yet. Please define one with `EventCollectionProvider::using(\$provider)`.");
		}

		return $provider();
	}

	/**
	 * Clears the provider.
	 */
	static public function clear()
	{
		self::$provider = null;
	}
}
