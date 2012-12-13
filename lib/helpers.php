<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Event;

use ICanBoogie\Events;

/**
 * Attaches an event hook.
 *
 * @param string $type The event type.
 * @param callable $hook The event hook.
 *
 * @return EventHook An {@link ICanBoogie\EventHook} instance that can be used to easily detach
 * the event hook.
 */
function attach($name, $hook=null)
{
	return Helpers::attach($name, $hook);
}

/**
 * Detaches an event hook.
 *
 * @param string $type The event type.
 * @param callable $hook The event hook.
 *
 * @return void
 *
 * @throws \Exception when the event hook is not attached to the event name.
 */
function detach($name, $hook)
{
	Helpers::detach($name, $hook);
}

/**
 * Patchable helpers of the ICanBoogie package.
 */
class Helpers
{
	static private $jumptable = array
	(
		'attach' => array(__CLASS__, 'attach'),
		'detach' => array(__CLASS__, 'detach')
	);

	/**
	 * Calls the callback of a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param array $arguments Arguments.
	 *
	 * @return mixed
	 */
	static public function __callstatic($name, array $arguments)
	{
		return call_user_func_array(self::$jumptable[$name], $arguments);
	}

	/**
	 * Patches a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param collable $callback Callback.
	 *
	 * @throws \RuntimeException is attempt to patch an undefined function.
	 */
	static public function patch($name, $callback)
	{
		if (empty(self::$jumptable[$name]))
		{
			throw new \RuntimeException("Undefined patchable: $name.");
		}

		self::$jumptable[$name] = $callback;
	}

	static private function attach($name, $hook=null)
	{
		return Events::get()->attach($name, $hook);
	}

	static private function detach($name, $hook=null)
	{
		Events::get()->detach($name, $hook);
	}
}