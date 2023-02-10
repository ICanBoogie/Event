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

use LogicException;

/**
 * Provides an {@link EventCollection} instance.
 */
class EventCollectionProvider
{
    /**
     * @var callable|null {@link EventCollection} provider
     */
    private static $provider;

    /**
     * Defines the {@link EventCollection} provider.
     *
     * @return ?callable
     *     The previous provider, or `null` if none was defined.
     */
    public static function define(callable $provider): ?callable
    {
        $previous = self::$provider;

        self::$provider = $provider;

        return $previous;
    }

    /**
     * Returns the current provider.
     */
    public static function defined(): ?callable
    {
        return self::$provider;
    }

    /**
     * Undefine the provider.
     */
    public static function undefine(): void
    {
        self::$provider = null;
    }

    /**
     * Returns a {@link EventCollection} instance using the provider.
     */
    public static function provide(): EventCollection
    {
        $provider = self::$provider
            ?? throw new LogicException(
                "No provider is defined yet. Please define one with `EventCollectionProvider::define(\$provider)`."
            );

        return $provider();
    }
}
