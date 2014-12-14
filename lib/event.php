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
 * An event.
 *
 * @property-read $stopped bool `true` when the event was stopped, `false` otherwise.
 * @property-read $used int The number of event hooks that were invoked while dispatching the event.
 * @property-read $used_by array Event hooks that were invoked while dispatching the event.
 * @property-read $target mixed The object the event is dispatched on.
 */
class Event
{
	/**
	 * The reserved properties that cannot be used to provide event properties.
	 *
	 * @var array[string]bool
	 */
	static private $reserved = array('chain' => true, 'stopped' => true, 'target' => true, 'used' => true, 'used_by' => true);

	/**
	 * Profiling information about events.
	 *
	 * @var array
	 */
	static public $profiling = array
	(
		'hooks' => array(),
		'unused' => array()
	);

	/**
	 * `true` when the event was stopped, `false` otherwise.
	 *
	 * @var bool
	 */
	private $stopped = false;

	/**
	 * Event hooks that were invoked while dispatching the event.
	 *
	 * @var array
	 */
	private $used_by = array();

	/**
	 * The object the event is dispatched on.
	 *
	 * @var mixed
	 */
	private $target;

	/**
	 * Chain of hooks to execute once the event has been fired.
	 *
	 * @var array
	 */
	private $chain = array();

	/**
	 * Creates an event and fires it immediately.
	 *
	 * If the event's target is specified its class is used to prefix the event type. For example,
	 * if the event's target is an instance of `ICanBoogie\Operation` and the event type is
	 * `process` the final event type will be `ICanBoogie\Operation::process`.
	 *
	 * @param mixed $target The target of the event.
	 * @param string $type The event type.
	 * @param array $payload Event payload.
	 *
	 * @throws PropertyIsReserved in attempt to specify a reserved property with the payload.
	 */
	public function __construct($target, $type, array $payload=array())
	{
		if ($target)
		{
			$class = get_class($target);
			$type = $class . '::' . $type;
		}

		$events = Events::get();

		if ($events->is_skippable($type))
		{
			return;
		}

		$hooks = $events->get_hooks($type);

		if (!$hooks)
		{
			self::$profiling['unused'][] = array(microtime(true), $type);

			$events->skip($type);

			return;
		}

		$this->target = $target;

		#
		# copy payload to the event's properties.
		#

		foreach ($payload as $property => &$value)
		{
			if (isset(self::$reserved[$property]))
			{
				throw new PropertyIsReserved($property);
			}

			#
			# we need to set the property to null before we set its value by reference
			# otherwise if the property doesn't exists the magic method `__get()` is
			# invoked and throws an exception because we try to get the value of a
			# property that do not exists.
			#

			$this->$property = null;
			$this->$property = &$value;
		}

		#
		# process event hooks chain
		#

		foreach ($hooks as $hook)
		{
			$this->used_by[] = $hook;
			$events->used($type, $hook);

			$time = microtime(true);

			call_user_func($hook, $this, $target);

			self::$profiling['hooks'][] = array($time, $type, $hook, microtime(true) - $time);

			if ($this->stopped)
			{
				return;
			}
		}

		#
		# process finish chain hooks
		#

		foreach ($this->chain as $hook)
		{
			$this->used_by[] = $hook;
			$events->used($type, $hook);

			$time = microtime(true);

			call_user_func($hook, $this, $target);

			self::$profiling['hooks'][] = array($time, $type, $hook, microtime(true) - $time);

			if ($this->stopped)
			{
				return;
			}
		}
	}

	/**
	 * Handles the read-only properties {@link $stopped}, {@link $used}, {@link $used_by}
	 * and {@link $target}.
	 *
	 * @param string $property The read-only property to return.
	 *
	 * @throws PropertyNotReadable if the property exists but is not readable.
	 * @throws PropertyNotDefined if the property doesn't exists.
	 *
	 * @return mixed
	 */
	public function __get($property)
	{
		static $readers = array('stopped', 'used_by', 'target');

		if ($property === 'used')
		{
			return count($this->used_by);
		}
		else if (in_array($property, $readers))
		{
			return $this->$property;
		}

		$properties = get_object_vars($this);

		if (array_key_exists($property, $properties))
		{
			throw new PropertyNotReadable(array($property, $this));
		}

		throw new PropertyNotDefined(array($property, $this));
	}

	/**
	 * Stops the hooks chain.
	 *
	 * After the `stop()` method is called the hooks chain is broken and no other hook is called.
	 */
	public function stop()
	{
		$this->stopped = true;
	}

	/**
	 * Add an event hook to the finish chain.
	 *
	 * The finish chain is executed after the event chain was traversed without being stopped.
	 *
	 * @param callable $hook
	 *
	 * @return \ICanBoogie\Event
	 */
	public function chain($hook)
	{
		$this->chain[] = $hook;

		return $this;
	}
}
