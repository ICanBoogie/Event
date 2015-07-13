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
 * Profiling information about events.
 */
final class EventProfiler
{
	/**
	 * Unused event types.
	 *
	 * ```php
	 * list($time, $type) = $value;
	 * ````
	 *
	 * @var array
	 */
	static public $unused = [];

	/**
	 * Event hooks calls.
	 *
	 * ```php
	 * list($time, $type, $hook, $started_at);
	 * ```
	 *
	 * @var array
	 */
	static public $calls = [];

	/**
	 * Adds an unused event type.
	 *
	 * @param string $type
	 */
	static public function add_unused($type)
	{
		self::$unused[] = [ microtime(true), $type ];
	}

	/**
	 * Adds an event hook call.
	 *
	 * @param string $type
	 * @param callable $hook
	 * @param double $started_at
	 */
	static public function add_call($type, $hook, $started_at)
	{
		self::$calls[] = [ microtime(true), $type, $hook, $started_at ];
	}
}
