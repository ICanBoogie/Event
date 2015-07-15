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
 * @method static EventCollection get()
 */
class EventCollection implements \IteratorAggregate
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
	 * @return EventCollection
	 */
	static protected function patchable_get()
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

	/**
	 * @param array $definitions Event hooks grouped by type.
	 */
	public function __construct(array $definitions = [])
	{
		$this->attach_many($definitions);
	}

	/**
	 * Returns an iterator for event hooks.
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->hooks);
	}

	protected function revoke_traces()
	{
		$this->consolidated_hooks = [];
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
	 * @param string $type Event type or closure.
	 * @param callable $hook The event hook, or nothing if $type is a closure.
	 *
	 * @return EventHook An event hook reference that can be used to easily detach the event
	 * hook.
	 *
	 * @throws \InvalidArgumentException when `$hook` is not a callable.
	 */
	public function attach($type, $hook = null)
	{
		list($type, $hook) = self::resolve_type_and_hook($type, $hook);

		if (!isset($this->hooks[$type]))
		{
			$this->hooks[$type] = [];
		}

		array_unshift($this->hooks[$type], $hook);

		#
		# If the event is a targeted event, we reset the skippable and consolidated hooks arrays.
		#

		$this->skippable = [];

		if (strpos($type, '::') !== false)
		{
			$this->consolidated_hooks = [];
		}

		return new EventHook($this, $type, $hook);
	}

	/**
	 * Attaches many event hooks grouped by event type.
	 *
	 * @param array $definitions
	 */
	public function attach_many(array $definitions)
	{
		$hooks = $this->hooks;

		foreach ($definitions as $type => $type_hooks)
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
	 * @param mixed $type
	 * @param mixed $hook
	 *
	 * @return EventHook
	 */
	public function once($type, $hook = null)
	{
		list($type, $hook) = self::resolve_type_and_hook($type, $hook);

		/* @var $eh EventHook */

		$eh = $this->attach($type, function($e, $t) use ($hook, &$eh) {

			call_user_func($hook, $e, $t);
			$eh->detach();

		});

		return $eh;
	}

	/**
	 * Resolves type and hook.
	 *
	 * @param string $type
	 * @param callable|null $hook
	 *
	 * @return array
	 */
	static private function resolve_type_and_hook($type, $hook)
	{
		if ($hook === null)
		{
			$hook = $type;
			$type = null;
		}

		self::assert_callable($hook);

		if ($type === null)
		{
			$type = self::resolve_event_type_from_hook($hook);
		}

		return [ $type, $hook ];
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
