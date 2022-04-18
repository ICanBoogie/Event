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

use function func_num_args;
use function ICanBoogie\Event\qualify_type;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * An event.
 *
 * @property-read bool $stopped `true` when the event was stopped, `false` otherwise.
 */
class Event
{
	/**
	 * @uses get_stopped
	 */
	use AccessorTrait;

	/**
	 * The object the event is dispatched on.
	 */
	public readonly ?object $target;

	/**
	 * Event unqualified type e.g. `recover`.
	 */
	public readonly string $unqualified_type;

	/**
	 * Event qualified type. e.g. `Exception::recover`
	 */
	public readonly string $qualified_type;

	/**
	 * `true` when the event was stopped, `false` otherwise.
	 */
	private bool $stopped = false;

	private function get_stopped(): bool
	{
		return $this->stopped;
	}

	/**
	 * Creates an event and fires it immediately.
	 *
	 * If the event's target is specified its class is used to prefix the event type. For example,
	 * if the event's target is an instance of `ICanBoogie\Operation` and the event type is
	 * `process` the final event type will be `ICanBoogie\Operation::process`.
	 *
	 * @param object|null $target The target of the event.
	 * @param string $type The event type.
	 */
	public function __construct(?object $target, string $type)
	{
		if (func_num_args() === 3) {
			trigger_error("The 'payload' parameter is no longer supported, better write an event class.", E_USER_DEPRECATED);
		}

		$qualified_type = $target ? qualify_type($target, $type) : $type;

		$this->target = $target;
		$this->unqualified_type = $type;
		$this->qualified_type = $qualified_type;
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
	 * Chain of hooks to execute once the event has been fired.
	 *
	 * @var callable[]
	 *
	 * @internal
	 */
	public array $__internal_chain = [];

	/**
	 * Add an event hook to the finish chain.
	 *
	 * The finish chain is executed after the event chain was traversed without being stopped.
	 *
	 * @phpstan-param (callable(Event, ?object): void) $hook
	 *
	 * @return $this
	 */
	public function chain(callable $hook): static
	{
		$this->__internal_chain[] = $hook;

		return $this;
	}
}
