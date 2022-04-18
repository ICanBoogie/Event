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
use ReflectionException;
use Throwable;

use function func_num_args;
use function get_called_class;
use function ICanBoogie\Event\qualify_type;
use function microtime;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * An event.
 *
 * @property-read bool $stopped `true` when the event was stopped, `false` otherwise.
 * @property-read int $used The number of event hooks that were invoked while dispatching the event.
 * @property-read callable[] $used_by Event hooks that were invoked while dispatching the event.
 * @property-read ?object $target The object the event is dispatched on.
 */
class Event
{
	/**
	 * @uses get_stopped
	 * @uses get_used_by
	 * @uses get_used
	 * @uses get_target
	 */
	use AccessorTrait;

	/**
	 * The reserved properties that cannot be used to provide event properties.
	 */
	private const RESERVED = [

		'chain' => true,
		'stopped' => true,
		'target' => true,
		'used' => true,
		'used_by' => true

	];

	/**
	 * Returns an unfired, initialized event.
	 *
	 * @param array<string, mixed> $params
	 *     Where _key_ is an attribute and _value_ its value.
	 *
	 * @throws ReflectionException
	 *
	 * @see EventReflection::from
	 */
	static public function from(array $params): static
	{
		$reflection = EventReflection::from(get_called_class());

		return $reflection->with($params); // @phpstan-ignore-line
	}

	/**
	 * `true` when the event was stopped, `false` otherwise.
	 */
	private bool $stopped = false;

	private function get_stopped(): bool
	{
		return $this->stopped;
	}

	/**
	 * Event hooks that were invoked while dispatching the event.
	 *
	 * @var callable[]
	 * @phpstan-var (callable(Event, ?object): void)[]
	 */
	private array $used_by = [];

	private function get_used_by(): array
	{
		return $this->used_by;
	}

	private function get_used(): int
	{
		return count($this->used_by);
	}

	/**
	 * The object the event is dispatched on.
	 */
	private ?object $target;

	protected function get_target(): ?object
	{
		return $this->target;
	}

	private string $event_type;

	/**
	 * Chain of hooks to execute once the event has been fired.
	 *
	 * @var array
	 */
	private array $chain = [];

	/**
	 * Whether the event fire should be fired immediately.
	 */
	private bool $no_immediate_fire = false;

	/**
	 * Creates an event and fires it immediately.
	 *
	 * If the event's target is specified its class is used to prefix the event type. For example,
	 * if the event's target is an instance of `ICanBoogie\Operation` and the event type is
	 * `process` the final event type will be `ICanBoogie\Operation::process`.
	 *
	 * @param object|null $target The target of the event.
	 * @param string $type The event type.
	 *
	 * @throws PropertyIsReserved in attempt to specify a reserved property with the payload.
	 */
	public function __construct(?object $target, string $type)
	{
		if (func_num_args() === 3) {
			trigger_error("The 'payload' parameter is no longer supported, better write an event class.", E_USER_DEPRECATED);
		}

		if ($target) {
			$type = qualify_type($type, $target);
		}

		$this->target = $target;
		$this->event_type = $type;

		if ($this->no_immediate_fire) {
			return;
		}

		$this->fire();
	}

	/**
	 * Fires the event.
	 */
	public function fire(): void
	{
		$target = $this->target;
		$type = $this->event_type;
		$events = get_events();

		if ($events->is_skippable($type)) {
			return;
		}

		$hooks = $events->get_hooks($type);

		if (!$hooks) {
			EventProfiler::add_unused($type);

			$events->skip($type);

			return;
		}

		$this->process_chain($hooks, $events, $type, $target);

		if ($this->stopped || !$this->chain) {
			return;
		}

		$this->process_chain($this->chain, $events, $type, $target);
	}

	/**
	 * Process an event chain.
	 *
	 * @phpstan-param (callable(Event, ?object): void)[] $chain
	 *
	 * @throws Throwable the exception of the event hook.
	 */
	private function process_chain(iterable $chain, EventCollection $events, string $type, ?object $target): void
	{
		foreach ($chain as $hook) {
			$started_at = microtime(true);

			try {
				$hook($this, $target);
			} finally {
				$this->used_by[] = [ $hook, $started_at, microtime(true) ];
				EventProfiler::add_call($type, $events->resolve_original_hook($hook), $started_at);
			}

			if ($this->stopped) {
				return;
			}
		}
	}

	/**
	 * Stops the hooks chain.
	 *
	 * After the `stop()` method is called the hooks chain is broken and no other hook is called.
	 */
	public function stop(): void
	{
		$this->stopped = true;
	}

	/**
	 * Add an event hook to the finish chain.
	 *
	 * The finish chain is executed after the event chain was traversed without being stopped.
	 *
	 * @phpstan-param (callable(Event, ?object): void) $hook
	 */
	public function chain(callable $hook): Event
	{
		$this->chain[] = $hook;

		return $this;
	}
}
