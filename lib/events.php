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
 * Events collected from the "hooks" config or attached by the user.
 */
class Events implements \IteratorAggregate, \ArrayAccess
{
	/**
	 * Singleton instance of the class.
	 *
	 * @var Events
	 */
	static protected $instance;

	/**
	 * Callback to initialize events.
	 *
	 * @var callable
	 */
	static public $initializer;

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return Events
	 */
	static public function get()
	{
		if (!self::$instance)
		{
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Synthesizes events config.
	 *
	 * Events are retrieved from the "hooks" config, under the "events" namespace.
	 *
	 * @param array $fragments
	 * @throws \InvalidArgumentException when a callback is not properly defined.
	 *
	 * @return array[string]array
	 */
	static public function synthesize_config(array $fragments)
	{
		$events = array();

		foreach ($fragments as $path => $fragment)
		{
			if (empty($fragment['events']))
			{
				continue;
			}

			foreach ($fragment['events'] as $type => $callback)
			{
				if (!is_string($callback))
				{
					throw new \InvalidArgumentException(format
					(
						'Event callback must be a string, %type given: :callback in %path', array
						(
							'type' => gettype($callback),
							'callback' => $callback,
							'path' => $path . 'config/hooks.php'
						)
					));
				}

				#
				# because modules are ordered by weight (most important are first), we can
				# push callbacks instead of unshifting them.
				#

				if (strpos($type, '::'))
				{
					list($class, $type) = explode('::', $type);

					$events[$class][$type][] = $callback;
				}
				else
				{
					$events['::'][$type][] = $callback;
				}
			}
		}

		return $events;
	}

	/**
	 * Event collection.
	 *
	 * @var array[string]array
	 */
	protected $events = array();

	/**
	 * Calls the event initializer if it is defined.
	 *
	 * @see Events::$initializer
	 */
	protected function __construct()
	{
		if (self::$initializer)
		{
			$this->events = call_user_func(self::$initializer, $this);
		}
	}

	/**
	 * Returns an iterator for event callbacks.
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->events);
	}

	/**
	 * Checks if a callback exists for a event.
	 */
	public function offsetExists($offset)
	{
		return isset($this->events[$offset]);
	}

	/**
	 * Returns the callbacks for a event.
	 */
	public function offsetGet($offset)
	{
		return $this->offsetExists($offset) ? $this->events[$offset] : array();
	}

	/**
	 * @throws OffsetNotWritable in attempt to set an offset.
	 */
	public function offsetSet($offset, $value)
	{
		throw new OffsetNotWritable(array($offset, $this));
	}

	/**
	 * @throws OffsetNotWritable in attempt to unset an offset.
	 */
	public function offsetUnset($offset)
	{
		throw new OffsetNotWritable(array($offset, $this));
	}

	/**
	 * Lists of skippable event types.
	 *
	 * @var array[string]bool
	 */
	protected $skippable = array();

	/**
	 * Marks an event type as skippable.
	 *
	 * @param string $type
	 */
	public function skip($type)
	{
		$this->skippable[$type] = true;
	}

	/**
	 * Returns whether or not an event has been marked as skippable.
	 *
	 * @param string $type
	 *
	 * @return boolean `true` if the event can be skipped, `false` otherwise.
	 */
	public function is_skippable($type)
	{
		return isset($this->skippable[$type]);
	}

	/**
	 * Attaches a hook to an event type.
	 *
	 * @param string $type
	 * @param callable $callback
	 *
	 * @return EventHook An {@link EventHook} instance that can be used to easily detach the event
	 * hook.
	 *
	 * @throws \InvalidArgumentException when $callback is not a callable.
	 */
	static public function attach($type, $callback)
	{
		if (!is_callable($callback))
		{
			throw new \InvalidArgumentException(format
			(
				'Event callback must be a callable, %type given: :callback', array
				(
					'type' => gettype($callback),
					'callback' => $callback
				)
			));
		}

		$events = static::get();
		$events->skippable = array();
		$ns = '::';
		$fulltype = $type;

		if (strpos($type, '::'))
		{
			list($ns, $type) = explode('::', $type);

			$events->events_by_class = array();
		}

		if (!isset($events->events[$ns][$type]))
		{
			$events->events[$ns][$type] = array();
		}

		array_unshift($events->events[$ns][$type], $callback);

		return new EventHook($events, $fulltype, $callback);
	}

	/**
	 * Detaches an event callback from an event type.
	 *
	 * @param string $type The type of the event.
	 * @param callable $callback The event callback.
	 *
	 * @return void
	 *
	 * @throws Exception when the event callback doesn't exists.
	 */
	static public function detach($type, $callback)
	{
		$ns = '::';

		if (strpos($type, '::'))
		{
			list($ns, $type) = explode('::', $type);
		}

		$events = static::get();

		if (isset($events->events[$ns][$type]))
		{
			foreach ($events->events[$ns][$type] as $key => $c)
			{
				if ($c != $callback)
				{
					continue;
				}

				unset($events->events[$ns][$type][$key]);

				if ($ns != '::')
				{
					$events->events_by_class = array();
				}

				return;
			}
		}

		throw new \Exception("Unknown event hook: {$type}.");
	}

	/**
	 * Returns the event types associated with a class.
	 *
	 * @param string $class
	 *
	 * @return array
	 */
	public function get_class_events($class)
	{
		if (isset($this->events_by_class[$class]))
		{
			return $this->events_by_class[$class];
		}

		$events = array();
		$c = $class;

		while ($c)
		{
			if (isset($this->events[$c]))
			{
				$events = \array_merge_recursive($events, $this->events[$c]);
			}

			$c = get_parent_class($c);
		}

		$this->events_by_class[$class] = $events;

		return $events;
	}

	protected $events_by_class = array();
}

/**
 * An event hook.
 *
 * An {@link EventHook} instance is created when an event hook is attached to the events. The
 * purpose of this instance is to ease detaching:
 *
 * <pre>
 * <?php
 *
 * $eh = Events::attach('ICanBoogie\HTTP\Dispatcher::collect', function(ICanBoogie\HTTP\Dispatcher\CollectEvent $event) {
 *
 *     // …
 *
 * });
 *
 * $eh->detach();
 * </pre>
 */
class EventHook
{
	private $events;
	private $type;
	private $callback;

	public function __construct(Events $events, $type, $callback)
	{
		$this->events = $events;
		$this->type = $type;
		$this->callback = $callback;
	}

	/**
	 * Detaches the event hook from the events.
	 */
	public function detach()
	{
		Events::detach($this->type, $this->callback);
	}
}