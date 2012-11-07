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
 * @property-read $stopped bool The {@link $stopped} property is readable.
 * @property-read $used int The {@link $used} property is readable.
 * @property-read $target mixed The {@link $target} property is readable.
 */
abstract class Event
{
	/**
	 * The reserved properties that cannot be used to provide event properties.
	 *
	 * @var array[string]bool
	 */
	static private $reserved = array('chain' => true, 'stopped' => true, 'target' => true, 'used' => true);

	/**
	 * Profiling information about events.
	 *
	 * @var array
	 */
	static public $profiling = array
	(
		'callbacks' => array(),
		'unused' => array()
	);

	/**
	 * `true` when the event was stopped.
	 *
	 * @var bool
	 */
	private $stopped = false;

	/**
	 * The number of callbacks called.
	 *
	 * @var int
	 */
	private $used = 0;

	/**
	 * The object target of the event.
	 *
	 * @var mixed
	 */
	private $target;

	/**
	 * Chain of callbacks to execute once the event has been fired.
	 *
	 * @var array
	 */
	private $chain = array();

	/**
	 * Creates an event and fires it immediately.
	 *
	 * If `$target` is provided the callbacks are narrowed to classes events and callbacks are
	 * called with `$target` as second parameter.
	 *
	 * @param mixed $target The target of the event.
	 * @param string $type The event type.
	 * @param array $payload Event payload.
	 *
	 * @throws PropertyIsReserved in attempt to specify a reserved property.
	 */
	public function __construct($target, $type, array $payload)
	{
		$this->target = $target;

		$events = Events::get();

		#
		# filters events according to the target.
		#

		if ($target)
		{
			$class = get_class($target);
			$complete_type = $class . '::' . $type;
			$filtered_events = $events->get_class_events($class);
		}
		else
		{
			$complete_type = $type;
			$filtered_events = $events['::'];
		}

		if ($events->is_skippable($complete_type))
		{
			return;
		}

		$prepared = false;

		foreach ($filtered_events as $pattern => $callbacks)
		{
			if ($pattern != $type)
			{
				continue;
			}

			if (!$prepared)
			{
				foreach ($payload as $property => &$value)
				{
					if (isset(self::$reserved[$property]))
					{
						throw new PropertyIsReserved($property);
					}

					#
					# we need to set the property to null before we set its value by reference
					# otherwise if the property doesn't exists the magic method {@link __get()} is
					# invoked and throws an exception because we try to get the value of a
					# property that does not exists.
					#

					$this->$property = null;
					$this->$property = &$value;
				}

				$prepared = true;
			}

			foreach ($callbacks as $callback)
			{
				++$this->used;

				$time = microtime(true);

				call_user_func($callback, $this, $target);

				self::$profiling['callbacks'][] = array($time, $complete_type, $callback, microtime(true) - $time);

				if ($this->stopped)
				{
					return;
				}
			}

			foreach ($this->chain as $callback)
			{
				++$this->used;

				$time = microtime(true);

				call_user_func($callback, $this, $target);

				self::$profiling['callbacks'][] = array($time, $type, $callback, microtime(true) - $time);

				if ($this->stopped)
				{
					return;
				}
			}
		}

		if (!$this->used)
		{
			self::$profiling['unused'][] = array(microtime(true), $complete_type);

			$events->skip($complete_type);
		}
	}

	/**
	 * Returns the read-only properties {@link $stopped}, {@link $used} and {@link $target}.
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
		static $readers = array('stopped', 'used', 'target');

		if (in_array($property, $readers))
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
	 * Stops the callbacks chain.
	 *
	 * After the `stop()` method is called the callback chain is broken and no other callback
	 * is called.
	 */
	public function stop()
	{
		$this->stopped = true;
	}

	/**
	 * Add a callback to the finish chain.
	 *
	 * The finish chain is executed after the event chain was traversed without being stopped.
	 *
	 * @param callable $callback
	 *
	 * @return \ICanBoogie\Event
	 */
	public function chain($callback)
	{
		$this->chain[] = $callback;

		return $this;
	}
}