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

use function microtime;

/**
 * Profiling information about events.
 */
final class EventProfiler
{
    /**
     * Unused event types.
     *
     * ```php
     * [ $time, $type ] = $value;
     * ````
     *
     * @var array<array{ float, string }>
     */
    public static array $unused = [];

    /**
     * Adds an unused event type.
     */
    public static function add_unused(string $type): void
    {
        self::$unused[] = [ microtime(true), $type ];
    }

    /**
     * Event hooks calls.
     *
     * ```php
     * [ $time, $type, $hook, $started_at ] = $value;
     * ```
     *
     * @var array<array{ float, string, callable, float }>
     */
    public static array $calls = [];

    /**
     * Adds an event hook call.
     */
    public static function add_call(string $type, callable $hook, float $started_at): void
    {
        self::$calls[] = [ microtime(true), $type, $hook, $started_at ];
    }
}
