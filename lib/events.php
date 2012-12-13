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
class Events implements \IteratorAggregate
{
	/**
	 * Singleton instance of the class.
	 *
	 * @var Events
	 */
	static protected $instance;

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
	 * Event collection.
	 *
	 * @var array[string]array
	 */
	protected $hooks = array();

	/**
	 * Event hooks consolidated by class and type.
	 *
	 * @var array[string]array
	 */
	protected $consolidated_hooks = array();

	/**
	 * Lists of skippable events.
	 *
	 * @var array[string]bool
	 */
	protected $skippable = array();

	/**
	 * Returns an iterator for event hooks.
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->hooks);
	}

	/**
	 * Attaches an event hook.
	 *
	 * The name of the event is resolved from the parameters of the event hook. Consider the
	 * following code:
	 *
	 * <pre>
	 * <?php
	 *
	 * $events->attach(function(ICanBoogie\Operation\BeforeProcessEvent $event, ICanBoogie\SaveOperation $target) {
	 *
	 *     // …
	 *
	 * });
	 * </pre>
	 *
	 * The hook will be attached to the `ICanBoogie\SaveOperation::process:before` event.
	 *
	 * @param callable $hook The event hook.
	 *
	 * @return EventHook An {@link EventHook} instance that can be used to easily detach the event
	 * hook.
	 *
	 * @throws \InvalidArgumentException when `$hook` is not a callable.
	 */
	public function attach($name, $hook=null)
	{
		if ($hook === null)
		{
			$hook = $name;
			$name = null;
		}

		if (!is_callable($hook))
		{
			throw new \InvalidArgumentException(format
			(
				'The event hook must be a callable, %type given: :hook', array
				(
					'type' => gettype($hook),
					'hook' => $hook
				)
			));
		}

		if ($name === null)
		{
			$name = self::resolve_event_type_from_hook($hook);
		}

		if (!isset($this->hooks[$name]))
		{
			$this->hooks[$name] = array();
		}

		array_unshift($this->hooks[$name], $hook);

		#
		# If the event is a targeted event, we reset the skippable and consolidated hooks arrays.
		#

		$this->skippable = array();

		if (strpos($name, '::') !== false)
		{
			$this->consolidated_hooks = array();
		}

		return new EventHook($this, $name, $hook);
	}

	/**
	 * Resolve an event type using the parameters of the specified hook.
	 *
	 * @param callable $hook
	 *
	 * @return string
	 */
	static private function resolve_event_type_from_hook($hook)
	{
		if (is_array($hook))
		{
			$reflection = new \ReflectionMethod($hook[0], $hook[1]);
		}
		else if (is_string($hook) && strpos($hook, '::'))
		{
			list($class, $method) = explode('::', $hook);

			$reflection = new \ReflectionMethod($class, $method);
		}
		else
		{
			$reflection = new \ReflectionFunction($hook);
		}

		list($event, $target) = $reflection->getParameters();

		$event_class = self::get_parameter_class($event);
		$target_class = self::get_parameter_class($target);

		$event_class_base = basename('/' . strtr($event_class, '\\', '/'));
		$type = substr($event_class_base, 0, -5);

		if (strpos($event_class_base, 'Before') === 0)
		{
			$type = hyphenate(substr($type, 6)) . ':before';
		}
		else
		{
			$type = hyphenate($type);
		}

		$type = strtr($type, '-', '_');

		return $target_class . '::' . $type;
	}

	/**
	 * Returns the class of a parameter reflection.
	 *
	 * Contrary of the {@link ReflectionParameter::getClass()} method, the class does not need to
	 * be available to be successfully retrieved.
	 *
	 * @param \ReflectionParameter $param
	 *
	 * @return string|null
	 */
	static private function get_parameter_class(\ReflectionParameter $param)
	{
		if (!preg_match('#\[\s*(<[^>]+>)?\s*([^\s]+)#', $param, $matches))
		{
			return;
		}

		return $matches[2];
	}

	public function batch_attach(array $definitions)
	{
		$this->hooks = \array_merge_recursive($this->hooks, $definitions);
		$this->skippable = array();
		$this->consolidated_hooks = array();
	}

	/**
	 * Detaches an event hook.
	 *
	 * @param string $name The name of the event.
	 * @param callable $hook The event hook.
	 *
	 * @return void
	 *
	 * @throws Exception when the event hook is not attached to the event name.
	 */
	public function detach($name, $hook)
	{
		$hooks = &$this->hooks;

		if (isset($hooks[$name]))
		{
			foreach ($hooks[$name] as $key => $h)
			{
				if ($h != $hook)
				{
					continue;
				}

				unset($hooks[$name][$key]);

				if (!count($hooks[$name]))
				{
					unset($hooks[$name]);
				}

				if (strpos($name, '::') !== false)
				{
					$this->consolidated_hooks = array();
				}

				return;
			}
		}

		throw new \Exception("The specified event hook is not attached to `{$name}`.");
	}

	/**
	 * Marks an event as skippable.
	 *
	 * @param string $name The event name.
	 */
	public function skip($name)
	{
		$this->skippable[$name] = true;
	}

	/**
	 * Returns whether or not an event has been marked as skippable.
	 *
	 * @param string $name The event name.
	 *
	 * @return boolean `true` if the event can be skipped, `false` otherwise.
	 */
	public function is_skippable($name)
	{
		return isset($this->skippable[$name]);
	}

	/**
	 * Returns the event hooks attached to the specified event name.
	 *
	 * If the class of the event's target is provided, event hooks are filtered according to
	 * the class and its hierarchy.
	 *
	 * @param string $name The event name.
	 *
	 * @return array
	 */
	public function get_hooks($name)
	{
		if (!strpos($name, '::'))
		{
			return isset($this->hooks[$name]) ? $this->hooks[$name] : array();
		}

		if (isset($this->consolidated_hooks[$name]))
		{
			return $this->consolidated_hooks[$name];
		}

		list($class, $type) = explode('::', $name);

		$hooks = array();
		$c = $class;

		while ($c)
		{
			if (isset($this->hooks[$c . '::' . $type]))
			{
				$hooks = array_merge($hooks, $this->hooks[$c . '::' . $type]);
			}

			$c = get_parent_class($c);
		}

		$this->consolidated_hooks[$name] = $hooks;

		return $hooks;
	}
}

/**
 * An event hook.
 *
 * An {@link EventHook} instance is created when an event hook is attached. The purpose of this
 * instance is to ease its detaching:
 *
 * <pre>
 * <?php
 *
 * use ICanBoogie\HTTP\Dispatcher;
 *
 * $eh = Event\attach(function(Dispatcher\CollectEvent $event, Dispatcher $target) {
 *
 *     // …
 *
 * });
 *
 * $eh->detach();
 * </pre>
 *
 * @property-read Events $events Events collection.
 * @property-read string $type Event type
 * @property-read callable $hook Event hook.
 */
class EventHook
{
	private $type;
	private $hook;
	private $events;

	public function __construct(Events $events, $type, $hook)
	{
		$this->events = $events;
		$this->type = $type;
		$this->hook = $hook;
	}

	public function __get($property)
	{
		static $readers = array('events', 'type', 'hook');

		if (in_array($property, $readers))
		{
			return $this->$property;
		}

		throw new PropertyNotDefined(array($property, $this));
	}

	/**
	 * Detaches the event hook from the events.
	 */
	public function detach()
	{
		$this->events->detach($this->type, $this->hook);
	}
}