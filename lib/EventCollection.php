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
class EventCollection implements \IteratorAggregate
{
	/**
	 * @var callable
	 */
	static private $instance_provider;

	/**
	 * Set {@link EventCollection} instance provider.
	 *
	 * @param callable $provider
	 */
	static public function set_instance_provider(callable $provider)
	{
		self::$instance_provider = $provider;
	}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return EventCollection
	 */
	static public function get()
	{
		$provider = self::$instance_provider;

		if (!$provider)
		{
			self::$instance_provider = $provider = function() {

				static $instance;

				return $instance ?: $instance = new static;

			};
		}

		return $provider();
	}

	/**
	 * Resolves type and hook.
	 *
	 * @param string $type_or_hook
	 * @param callable|null $hook
	 *
	 * @return array Returns the type and hook.
	 */
	static private function resolve_type_and_hook($type_or_hook, $hook)
	{
		if ($hook === null)
		{
			$type = null;
			$hook = $type_or_hook;
		}
		else
		{
			$type = $type_or_hook;
		}

		EventHookReflection::assert_valid($hook);

		if ($type === null)
		{
			$type = EventHookReflection::from($hook)->type;
		}

		return [ $type, $hook ];
	}

	/**
	 * Event hooks by type.
	 *
	 * @var array
	 */
	protected $hooks = [];

	/**
	 * Event hooks by class and type.
	 *
	 * @var array
	 */
	protected $consolidated_hooks = [];

	/**
	 * Skippable events.
	 *
	 * @var array
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

	/**
	 * Revokes consolidated hooks and skippable types.
	 */
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
	 * @param string|callable $type_or_hook Event type or event hook.
	 * @param callable $hook The event hook, or nothing if $type is the event hook.
	 *
	 * @return EventHook An event hook reference that can be used to easily detach the event
	 * hook.
	 *
	 * @throws \InvalidArgumentException when `$hook` is not a callable.
	 */
	public function attach($type_or_hook, $hook = null)
	{
		list($type, $hook) = self::resolve_type_and_hook($type_or_hook, $hook);

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
	 * Attaches many event hooks at once.
	 *
	 * **Note**: The event hooks must be grouped by event type.
	 *
	 * @param array $definitions
	 */
	public function attach_many(array $definitions)
	{
		$hooks = $this->hooks;
		$intersect = array_intersect_key($definitions, $hooks);
		$hooks += array_diff_key($definitions, $hooks);

		foreach ($intersect as $type => $type_hooks)
		{
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
			throw new \InvalidArgumentException("attach_to() target must be an object.");
		}

		$type = EventHookReflection::from($hook)->type;

		return $this->attach($type, function($e, $t) use ($target, $hook) {

			if ($t !== $target)
			{
				return;
			}

			$hook($e, $t);

		});
	}

	/**
	 * Attaches an event hook that is detached once used.
	 *
	 * @param string|callable $type_or_hook Event type or event hook.
	 * @param callable $hook The event hook, or nothing if $type is the event hook.
	 *
	 * @return EventHook
	 *
	 * @see attach()
	 */
	public function once($type_or_hook, $hook = null)
	{
		list($type, $hook) = self::resolve_type_and_hook($type_or_hook, $hook);

		return $eh = $this->attach($type, function($e, $t) use ($hook, &$eh) {

			/* @var $eh EventHook */

			call_user_func($hook, $e, $t);

			$eh->detach();

		});
	}

	/**
	 * Detaches an event hook.
	 *
	 * @param string $type The name of the event.
	 * @param callable $hook The event hook.
	 *
	 * @throws \Exception when the event hook is not attached to the event name.
	 */
	public function detach($type, $hook)
	{
		$hooks = &$this->hooks;
		$key = $this->search_hook($type, $hook);

		if ($key === false)
		{
			throw new \LogicException("The specified event hook is not attached to `{$type}`.");
		}

		unset($hooks[$type][$key]);

		if (!count($hooks[$type]))
		{
			unset($hooks[$type]);
		}

		if (strpos($type, '::'))
		{
			$this->consolidated_hooks = [];
		}
	}

	/**
	 * Marks an event as skippable.
	 *
	 * @param string $type The event type.
	 */
	public function skip($type)
	{
		$this->skippable[$type] = true;
	}

	/**
	 * Returns whether or not an event has been marked as skippable.
	 *
	 * @param string $type The event type.
	 *
	 * @return boolean `true` if the event can be skipped, `false` otherwise.
	 */
	public function is_skippable($type)
	{
		return isset($this->skippable[$type]);
	}

	/**
	 * Returns the event hooks attached to the specified event type.
	 *
	 * @param string $type The event type.
	 *
	 * @return array
	 */
	public function get_hooks($type)
	{
		if (!strpos($type, '::'))
		{
			return isset($this->hooks[$type]) ? $this->hooks[$type] : [];
		}

		if (isset($this->consolidated_hooks[$type]))
		{
			return $this->consolidated_hooks[$type];
		}

		return $this->consolidated_hooks[$type] = $this->consolidate_hooks($type);
	}

	/**
	 * Searches an event hook.
	 *
	 * @param string $type The event type.
	 * @param callable $hook
	 *
	 * @return string|false The key of the event hook, or `false` if it not found.
	 */
	private function search_hook($type, $hook)
	{
		$hooks = $this->hooks;

		return empty($hooks[$type]) ? false : array_search($hook, $hooks[$type], true);
	}

	/**
	 * Consolidate hooks of a same type.
	 *
	 * If the class of the event's target is provided, event hooks are filtered according to
	 * the class and its hierarchy.
	 *
	 * @param string $type The event type.
	 *
	 * @return array
	 */
	private function consolidate_hooks($type)
	{
		list($class, $type) = explode('::', $type);

		$hooks = [];

		while ($class)
		{
			$k = $class . '::' . $type;

			if (isset($this->hooks[$k]))
			{
				$hooks = array_merge($hooks, $this->hooks[$k]);
			}

			$class = get_parent_class($class);
		}

		return $hooks;
	}
}
