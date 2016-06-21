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

use ICanBoogie\Accessor\AccessorTrait;

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
	use AccessorTrait;

	/**
	 * The reserved properties that cannot be used to provide event properties.
	 *
	 * @var array[string]bool
	 */
	static private $reserved = [ 'chain' => true, 'stopped' => true, 'target' => true, 'used' => true, 'used_by' => true ];

	/**
	 * Returns an unfired, initialized event.
	 *
	 * @see EventReflection::from
	 *
	 * @param array $params
	 *
	 * @return Event
	 */
	static public function from(array $params)
	{
		$reflection = EventReflection::from(get_called_class());

		return $reflection->with($params);
	}

	/**
	 * `true` when the event was stopped, `false` otherwise.
	 *
	 * @var bool
	 */
	private $stopped = false;

	protected function get_stopped()
	{
		return $this->stopped;
	}

	/**
	 * Event hooks that were invoked while dispatching the event.
	 *
	 * @var array
	 */
	private $used_by = [];

	protected function get_used_by()
	{
		return $this->used_by;
	}

	protected function get_used()
	{
		return count($this->used_by);
	}

	/**
	 * The object the event is dispatched on.
	 *
	 * @var mixed
	 */
	private $target;

	protected function get_target()
	{
		return $this->target;
	}

	/**
	 * Event type.
	 *
	 * @var string
	 */
	private $event_type;

	/**
	 * Chain of hooks to execute once the event has been fired.
	 *
	 * @var array
	 */
	private $chain = [];

	/**
	 * Whether the event fire should be fired immediately.
	 *
	 * @var bool
	 */
	private $no_immediate_fire = false;

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
	public function __construct($target, $type, array $payload = [])
	{
		if ($target)
		{
			$type = get_class($target) . '::' . $type;
		}

		$this->target = $target;
		$this->event_type = $type;

		if ($payload)
		{
			$this->map_payload($payload);
		}

		if ($this->no_immediate_fire)
		{
			return;
		}

		$this->fire();
	}

	/**
	 * Fires the event.
	 */
	public function fire()
	{
		$target = $this->target;
		$type = $this->event_type;
		$events = get_events();

		if ($events->is_skippable($type))
		{
			return;
		}

		$hooks = $events->get_hooks($type);

		if (!$hooks)
		{
			EventProfiler::add_unused($type);

			$events->skip($type);

			return;
		}

		$this->process_chain($hooks, $events, $type, $target);

		if ($this->stopped || !$this->chain)
		{
			return;
		}

		$this->process_chain($this->chain, $events, $type, $target);
	}

	/**
	 * Maps the payload to the event's properties.
	 *
	 * @param array $payload
	 *
	 * @throws PropertyIsReserved if a reserved property is used in the payload.
	 */
	private function map_payload(array $payload)
	{
		$reserved = array_intersect_key($payload, self::$reserved);

		if ($reserved)
		{
			throw new PropertyIsReserved(key($reserved));
		}

		foreach ($payload as $property => &$value)
		{
			#
			# we need to set the property to null before we set its value by reference
			# otherwise if the property doesn't exists the magic method `__get()` is
			# invoked and throws an exception because we try to get the value of a
			# property that do not exists.
			#

			$this->$property = null;
			$this->$property = &$value;
		}
	}

	/**
	 * Process an event chain.
	 *
	 * @param array $chain
	 * @param EventCollection $events
	 * @param string $type
	 * @param object|null $target
	 *
	 * @throws \Exception, the exception of the event hook.
	 */
	private function process_chain(array $chain, EventCollection $events, $type, $target)
	{
		foreach ($chain as $hook)
		{
			$started_at = microtime(true);

			try
			{
				call_user_func($hook, $this, $target);
			}
			catch (\Exception $e)
			{
				throw $e;
			}
			finally
			{
				$this->used_by[] = [ $hook, $started_at, microtime(true) ];
				EventProfiler::add_call($type, $events->resolve_original_hook($hook), $started_at);
			}

			if ($this->stopped)
			{
				return;
			}
		}
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
	 * @return Event
	 */
	public function chain($hook)
	{
		$this->chain[] = $hook;

		return $this;
	}
}
