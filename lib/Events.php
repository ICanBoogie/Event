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
 *
 * @method static Events get()
 */
class Events implements \IteratorAggregate
{
	static private $jumptable = [

		'get' => [ __CLASS__, 'patchable_get' ]

	];

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
	 * @param callable $callback Callback.
	 *
	 * @return callable Previous callable.
	 *
	 * @throws \RuntimeException is attempt to patch an undefined function.
	 */
	static public function patch($name, $callback)
	{
		if (empty(self::$jumptable[$name]))
		{
			throw new \RuntimeException("Undefined patchable: $name.");
		}

		$previous = self::$jumptable[$name];
		self::$jumptable[$name] = $callback;

		return $previous;
	}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return Events
	 */
	static private function patchable_get()
	{
		static $events;

		if (!$events)
		{
			$events = new static;
		}

		return $events;
	}

	/**
	 * @param mixed $hook
	 *
	 * @throws \InvalidArgumentException if `$hook` is not a callable
	 */
	static private function assert_callable($hook)
	{
		if (!is_callable($hook))
		{
			throw new \InvalidArgumentException(format
			(
				'The event hook must be a callable, %type given: :hook', [

					'type' => gettype($hook),
					'hook' => $hook

				]
			));
		}
	}

	/**
	 * Event collection.
	 *
	 * @var array[string]array
	 */
	protected $hooks = [];

	/**
	 * Event hooks consolidated by class and type.
	 *
	 * @var array[string]array
	 */
	protected $consolidated_hooks = [];

	/**
	 * Lists of skippable events.
	 *
	 * @var array[string]bool
	 */
	protected $skippable = [];

	private $once_collection = [];

	public function __construct(array $hooks = [])
	{
		$this->hooks = $hooks;
	}

	/**
	 * Returns an iterator for event hooks.
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->hooks);
	}

	/**
	 * Adds events from a configuration.
	 *
	 * @param array $config
	 */
	public function configure(array $config)
	{
		$hooks = $this->hooks;

		foreach ($config as $type => $type_hooks)
		{
			if (empty($hooks[$type]))
			{
				$hooks[$type] = $type_hooks;

				continue;
			}

			$hooks[$type] = array_merge($hooks[$type], $type_hooks);
		}

		$this->hooks = array_map('array_unique', $hooks);
		$this->revoke_traces();
	}

	protected function revoke_traces()
	{
		$this->consolidated_hooks = [];
		$this->once_collection = [];
		$this->skippable = [];
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
	 * @param string $name Event type or closure.
	 * @param callable $hook The event hook, or nothing if $name is a closure.
	 *
	 * @return EventHook An event hook reference that can be used to easily detach the event
	 * hook.
	 *
	 * @throws \InvalidArgumentException when `$hook` is not a callable.
	 */
	public function attach($name, $hook = null)
	{
		if ($hook === null)
		{
			$hook = $name;
			$name = null;
		}

		self::assert_callable($hook);

		if ($name === null)
		{
			$name = self::resolve_event_type_from_hook($hook);
		}

		if (!isset($this->hooks[$name]))
		{
			$this->hooks[$name] = [];
		}

		array_unshift($this->hooks[$name], $hook);

		#
		# If the event is a targeted event, we reset the skippable and consolidated hooks arrays.
		#

		$this->skippable = [];

		if (strpos($name, '::') !== false)
		{
			$this->consolidated_hooks = [];
		}

		return new EventHook($this, $name, $hook);
	}

	/**
	 * Attaches an event hook to a specific target.
	 *
	 * @param object $target
	 * @param callable $hook
	 *
	 * @return EventHook
	 */
	public function attach_to($target, $hook)
	{
		if (!is_object($target))
		{
			throw new \InvalidArgumentException("Target must be an object");
		}

		self::assert_callable($hook);

		$name = self::resolve_event_type_from_hook($hook);

		return $this->attach($name, function($e, $t) use ($target, $hook) {

			if ($t !== $target)
			{
				return;
			}

			$hook($e, $t);

		});
	}

	/**
	 * Attach an event hook that is detached once used.
	 *
	 * @see attach()
	 *
	 * @param mixed $name
	 * @param mixed $hook
	 *
	 * @return EventHook
	 */
	public function once($name, $hook = null)
	{
		$event_hook = $this->attach($name, $hook);

		$this->once_collection[$event_hook->type][] = $event_hook;

		return $event_hook;
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
			return null;
		}

		return $matches[2];
	}

	public function batch_attach(array $definitions)
	{
		$this->hooks = \array_merge_recursive($this->hooks, $definitions);
		$this->skippable = [];
		$this->consolidated_hooks = [];
	}

	/**
	 * Detaches an event hook.
	 *
	 * @param string $name The name of the event.
	 * @param callable $hook The event hook.
	 *
	 * @return void
	 *
	 * @throws \Exception when the event hook is not attached to the event name.
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
					$this->consolidated_hooks = [];
				}

				#
				# Remove the event from the once collection.
				#

				$once = &$this->once_collection;

				if (isset($once[$name]))
				{
					foreach ($once[$name] as $k => $event_hook)
					{
						if ($hook !== $event_hook->hook)
						{
							continue;
						}

						unset($once[$name][$k]);
					}
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
	 * Declare an event hook as _used_ by an event type, if the hook has been attached using {@link once()}
	 * it is removed.
	 *
	 * @param $type
	 * @param $hook
	 */
	public function used($type, $hook)
	{
		if (empty($this->once_collection[$type]))
		{
			return;
		}

		/* @var $event_hook EventHook */

		foreach ($this->once_collection[$type] as $k => $event_hook)
		{
			if ($hook !== $event_hook->hook)
			{
				continue;
			}

			$event_hook->detach();

			break;
		}
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
			return isset($this->hooks[$name]) ? $this->hooks[$name] : [];
		}

		if (isset($this->consolidated_hooks[$name]))
		{
			return $this->consolidated_hooks[$name];
		}

		list($class, $type) = explode('::', $name);

		$hooks = [];
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
 * $eh = $events->attach(function(Dispatcher\CollectEvent $event, Dispatcher $target) {
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
		static $readers = [ 'events', 'type', 'hook' ];

		if (in_array($property, $readers))
		{
			return $this->$property;
		}

		throw new PropertyNotDefined([ $property, $this ]);
	}

	/**
	 * Detaches the event hook from the events.
	 */
	public function detach()
	{
		$this->events->detach($this->type, $this->hook);
	}
}
