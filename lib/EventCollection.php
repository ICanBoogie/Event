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

use ArrayIterator;
use Closure;
use ICanBoogie\Event\Detach;
use InvalidArgumentException;
use IteratorAggregate;
use LogicException;
use ReflectionException;
use SplObjectStorage;
use Traversable;

use function array_diff_key;
use function array_intersect_key;
use function array_map;
use function array_merge;
use function array_unique;
use function array_unshift;
use function array_values;
use function count;
use function strpos;

/**
 * Events collected from the "hooks" config or attached by the user.
 */
class EventCollection implements IteratorAggregate
{
	/**
	 * Resolves type and hook.
	 *
	 * @phpstan-param string|(callable(Event, ?object): void) $type_or_hook
	 * @phpstan-param (callable(Event, ?object): void)|null $hook
	 *
	 * @return array{ 0: string, 1: callable } Returns the type and hook.
	 *
	 * @throws ReflectionException
	 */
	static private function resolve_type_and_hook(string|callable $type_or_hook, ?callable $hook): array
	{
		if ($hook === null) {
			$type = null;
			$hook = $type_or_hook;
		} else {
			$type = $type_or_hook;
		}

		EventHookReflection::assert_valid($hook);

		if ($type === null) {
			$type = EventHookReflection::from($hook)->type;
		}

		return [ $type, $hook ];
	}

	/**
	 * Event hooks by type.
	 *
	 * @var array<string, callable[]>
	 *     Where _key_ is an event type and _value_ an array of callables.
	 * @phpstan-var array<string, (callable(Event, ?object): void)[]>
	 */
	private array $hooks = [];

	/**
	 * Event hooks by class and type.
	 *
	 * @var array<string, callable[]>
	 *     Where _key_ is an event type and _value_ an array of callables.
	 * @phpstan-var array<string, (callable(Event, ?object): void)[]>
	 */
	private array $consolidated_hooks = [];

	private SplObjectStorage $original_hooks;

	/**
	 * Skippable events.
	 *
	 * @var array<string, true>
	 *     Where _key_ is an event type.
	 */
	private array $skippable = [];

	/**
	 * @param array $definitions Event hooks grouped by type.
	 */
	public function __construct(array $definitions = [])
	{
		$this->original_hooks = new SplObjectStorage();

		$this->attach_many($definitions);
	}

	/**
	 * Returns an iterator for event hooks.
	 */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->hooks);
	}

	/**
	 * Revokes consolidated hooks and skippable types.
	 */
	private function revoke_traces()
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
	 * $events->attach(function(ICanBoogie\Operation\BeforeProcessEvent $event,
	 * ICanBoogie\Module\Operation\SaveOperation $target) {
	 *
	 *     // …
	 *
	 * });
	 * </pre>
	 *
	 * The hook will be attached to the `ICanBoogie\Module\Operation\SaveOperation::process:before` event.
	 *
	 * @param Closure|string $type_or_hook Event type or event hook.
	 * @param Closure|null $hook The event hook, or nothing if $type is the event hook.
	 * @phpstan-param (Closure(Event, ?object): void)|null $hook
	 *
	 * @throws InvalidArgumentException when `$hook` is not a callable.
	 * @throws ReflectionException
	 */
	public function attach(Closure|string $type_or_hook, Closure $hook = null): Detach
	{
		[ $type, $hook ] = self::resolve_type_and_hook($type_or_hook, $hook);

		$this->hooks[$type] ??= [];
		array_unshift($this->hooks[$type], $hook);

		#
		# If the event is a targeted event, we reset the skippable and consolidated hooks arrays.
		#

		$this->skippable = [];

		if (str_contains($type, '::')) {
			// Reset consolidated hooks
			$this->consolidated_hooks = [];
		}

		return new Detach($this, $type, $hook);
	}

	/**
	 * Attaches many event hooks at once.
	 *
	 * **Note**: The event hooks must be grouped by event type.
	 *
	 * @param array $definitions
	 */
	public function attach_many(array $definitions): void
	{
		$hooks = &$this->hooks;
		$intersect = array_intersect_key($definitions, $hooks);
		$hooks += array_diff_key($definitions, $hooks);

		foreach ($intersect as $type => $type_hooks) {
			$hooks[$type] = array_merge($hooks[$type], $type_hooks);
		}

		$hooks = array_map(function ($callables) {
			return array_values(array_unique($callables, SORT_REGULAR));
		}, $hooks);

		$this->revoke_traces();
	}

	/**
	 * Attaches an event hook to a specific target.
	 *
	 * @template T of object
	 *
	 * @param T $target
	 * @phpstan-param (Closure(Event, T): void) $hook
	 *
	 * @throws ReflectionException
	 */
	public function attach_to(object $target, Closure $hook): Detach
	{
		$type = EventHookReflection::from($hook)->type;

		return $this->attach(
			$type,
			$this->shadow_original_hook($hook, function ($e, $t) use ($target, $hook) {
				if ($t !== $target) {
					return;
				}

				$hook($e, $t);
			})
		);
	}

	/**
	 * Attaches an event hook that is detached once used.
	 *
	 * @param Closure|string $type_or_hook Event type or event hook.
	 * @param Closure|null $hook The event hook, or nothing if $type is the event hook.
	 * @phpstan-param (Closure(Event, ?object): void)|null $hook
	 *
	 * @throws ReflectionException
	 */
	public function once(Closure|string $type_or_hook, Closure $hook = null): Detach
	{
		[ $type, $hook ] = self::resolve_type_and_hook($type_or_hook, $hook);

		$detach = $this->attach(
			$type,
			$this->shadow_original_hook($hook, function ($e, $t) use ($hook, &$detach) {
				$hook($e, $t);

				$detach();
			})
		);

		return $detach;
	}

	/**
	 * Detaches an event hook.
	 *
	 * @param string $type The name of the event.
	 * @param callable $hook The event hook.
	 *
	 * @throws LogicException when the event hook is not attached to the event name.
	 */
	public function detach(string $type, callable $hook): void
	{
		$hooks = &$this->hooks;
		$key = $this->search_hook($type, $hook);

		if ($key === false) {
			throw new LogicException("The specified event hook is not attached to `$type`.");
		}

		unset($hooks[$type][$key]);

		if (!count($hooks[$type])) {
			unset($hooks[$type]);
		}

		if (strpos($type, '::')) {
			$this->consolidated_hooks = [];
		}
	}

	/**
	 * Marks an event as skippable.
	 *
	 * @param string $type The event type.
	 */
	public function skip(string $type): void
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
	public function is_skippable(string $type): bool
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
	public function get_hooks(string $type): array
	{
		if (!strpos($type, '::')) {
			return $this->hooks[$type] ?? [];
		}

		if (isset($this->consolidated_hooks[$type])) {
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
	private function search_hook(string $type, callable $hook)
	{
		$hooks = $this->hooks;

		return empty($hooks[$type]) ? false : \array_search($hook, $hooks[$type], true);
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
	private function consolidate_hooks(string $type): array
	{
		[ $class, $type ] = \explode('::', $type);

		$hooks = [];

		while ($class) {
			$k = $class . '::' . $type;

			if (isset($this->hooks[$k])) {
				$hooks = array_merge($hooks, $this->hooks[$k]);
			}

			$class = \get_parent_class($class);
		}

		return $hooks;
	}

	/**
	 * Resolves original hook.
	 *
	 * @param callable $hook
	 *
	 * @return callable
	 */
	public function resolve_original_hook(callable $hook): callable
	{
		if (!\is_object($hook) || empty($this->original_hooks[$hook])) {
			return $hook;
		}

		return $this->original_hooks[$hook];
	}

	/**
	 * Adds a reference to the original hook.
	 *
	 * @param callable $hook
	 * @param \Closure $wrapper
	 *
	 * @return \Closure The wrapper.
	 */
	private function shadow_original_hook(callable $hook, \Closure $wrapper): \Closure
	{
		$this->original_hooks[$wrapper] = $hook;

		return $wrapper;
	}
}
