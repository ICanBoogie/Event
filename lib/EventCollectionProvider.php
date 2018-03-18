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
	 * @return callable The previous provider, or `null` if none was defined.
	 */
	static public function define(callable $provider): ?callable
	{
		$previous = self::$provider;

		self::$provider = $provider;

		return $previous;
	}

	/**
	 * Returns the current provider.
	 *
	 * @return callable|null
	 */
	static public function defined(): ?callable
	{
		return self::$provider;
	}

	/**
	 * Undefine the provider.
	 */
	static public function undefine(): void
	{
		self::$provider = null;
	}

	/**
	 * Returns a {@link EventCollection} instance using the provider.
	 *
	 * @return EventCollection
	 */
	static public function provide(): EventCollection
	{
		$provider = self::$provider;

		if (!$provider)
		{
			throw new \LogicException("No provider is defined yet. Please define one with `EventCollectionProvider::define(\$provider)`.");
		}

		return $provider();
	}
}
