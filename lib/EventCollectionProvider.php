<?php

namespace ICanBoogie;

use LogicException;

/**
 * Provides an {@see EventCollection} instance.
 */
class EventCollectionProvider
{
    /**
     * @var callable|null {@see EventCollection} provider
     */
    private static $provider;

    /**
     * Defines the {@see EventCollection} provider.
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
     * Returns a {@see EventCollection} instance using the provider.
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
