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
use ICanBoogie\Event\Config;
use ICanBoogie\Event\Detach;
use IteratorAggregate;
use LogicException;
use ReflectionException;
use SplObjectStorage;
use Throwable;
use Traversable;

use function array_diff_key;
use function array_intersect_key;
use function array_map;
use function array_merge;
use function array_search;
use function array_unique;
use function array_unshift;
use function array_values;
use function assert;
use function count;
use function explode;
use function get_parent_class;
use function is_callable;
use function is_object;
use function microtime;
use function strpos;

/**
 * Events collected from the "hooks" config or attached by the user.
 *
 * @implements IteratorAggregate<string, callable[]>
 */
class EventCollection implements IteratorAggregate
{
    /**
     * Resolves type and hook.
     *
     * @param (callable(Event, ?object): void)|string $type_or_hook
     * @param (callable(Event, ?object): void)|null $hook
     *
     * @return array{ string, (callable(Event, ?object): void) } Returns the type and hook.
     *
     * @throws ReflectionException
     */
    private static function resolve_type_and_hook(string|callable $type_or_hook, ?callable $hook): array
    {
        if ($hook === null) {
            $type = null;
            $hook = $type_or_hook;
        } else {
            $type = $type_or_hook;
        }

        EventHookReflection::assert_valid($hook);

        assert(is_callable($hook));

        if ($type === null) {
            $type = EventHookReflection::from($hook)->type;
        }

        assert(is_string($type));

        return [ $type, $hook ];
    }

    /**
     * Event hooks by type.
     *
     * @var array<string, (callable(Event, ?object): void)[]>
     *     Where _key_ is an event type and _value_ an array of callables.
     */
    private array $hooks = [];

    /**
     * Event hooks by class and type.
     *
     * @var array<string, (callable(Event, ?object): void)[]>
     *     Where _key_ is an event type and _value_ an array of callables.
     */
    private array $consolidated_hooks = [];

    /**
     * @var SplObjectStorage<Closure, callable>
     */
    private SplObjectStorage $original_hooks;

    /**
     * Skippable events.
     *
     * @var array<string, true>
     *     Where _key_ is an event type.
     */
    private array $skippable = [];

    public function __construct(Config $config = null)
    {
        $this->original_hooks = new SplObjectStorage();

        if ($config) {
            $this->attach_many($config);
        }
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
    private function revoke_traces(): void
    {
        $this->consolidated_hooks = [];
        $this->skippable = [];
    }

    /**
     * Attaches an event hook.
     *
     * @param Closure|string $type_or_hook
     *     Event type or event hook.
     * @param (Closure(Event, ?object): void)|null $hook
     *     The event hook, or nothing if $type is the event hook.
     *
     * @throws ReflectionException
     */
    public function attach(Closure|string $type_or_hook, Closure $hook = null): Detach
    {
        [ $type, $hook ] = self::resolve_type_and_hook($type_or_hook, $hook);

        assert($hook instanceof Closure);

        $this->hooks[$type] ??= [];
        array_unshift($this->hooks[$type], $hook);

        #
        # If the event has a sender, we reset the skippable and consolidated hooks arrays.
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
     */
    public function attach_many(Config $config): void
    {
        $hooks = &$this->hooks;
        $intersect = array_intersect_key($config->listeners, $hooks);
        $hooks += array_diff_key($config->listeners, $hooks);

        foreach ($intersect as $type => $type_hooks) {
            $hooks[$type] = array_merge($hooks[$type], $type_hooks);
        }

        $hooks = array_map(function ($callables) {
            return array_values(array_unique($callables, SORT_REGULAR));
        }, $hooks);

        $this->revoke_traces();
    }

    /**
     * Attaches an event hook to a specific sender.
     *
     * @template T of object
     *
     * @phpstan-param T $sender
     *
     * @param (Closure(Event, T): void) $hook
     *
     * @throws ReflectionException
     */
    public function attach_to(object $sender, Closure $hook): Detach
    {
        $type = EventHookReflection::from($hook)->type;

        return $this->attach(
            $type,
            $this->shadow_original_hook($hook, static function (Event $e, ?object $s) use ($sender, $hook): void {
                if ($s !== $sender) {
                    return;
                }

                $hook($e, $s);
            })
        );
    }

    /**
     * Attaches an event hook that is detached once used.
     *
     * @param (Closure(Event, ?object): void)|string $type_or_hook
     *     Event type or event hook.
     * @param (Closure(Event, ?object): void)|null $hook
     *     The event hook, or nothing if $type is the event hook.
     *
     * @throws ReflectionException
     */
    public function once(Closure|string $type_or_hook, Closure $hook = null): Detach
    {
        [ $type, $hook ] = self::resolve_type_and_hook($type_or_hook, $hook);

        $detach = $this->attach(
            $type,
            $this->shadow_original_hook($hook, static function (Event $e, ?object $s) use ($hook, &$detach): void {
                $hook($e, $s);

                /** @phpstan-ignore-next-line */
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
     * Fires the event.
     *
     * @template T of Event
     *
     * @phpstan-param T $event
     *
     * @return T
     *
     * @throws Throwable
     */
    public function emit(Event $event): Event
    {
        $sender = $event->sender ?? null;
        $type = $event->qualified_type;

        if ($this->is_skippable($type)) {
            return $event;
        }

        $hooks = $this->get_hooks($type);

        if (!$hooks) {
            EventProfiler::add_unused($type);

            $this->skip($type);

            return $event;
        }

        $this->process_chain($event, $hooks, $type, $sender);

        if ($event->stopped || !$event->internal_chain) {
            return $event;
        }

        $this->process_chain($event, $event->internal_chain, $type, $sender);

        return $event;
    }

    /**
     * Process an event chain.
     *
     * @param (callable(Event, ?object): void)[] $chain
     */
    private function process_chain(Event $event, iterable $chain, string $type, ?object $sender): void
    {
        foreach ($chain as $hook) {
            $started_at = microtime(true);

            try {
                $hook($event, $sender);
            } finally {
                EventProfiler::add_call($type, $this->resolve_original_hook($hook), $started_at);
            }

            if ($event->stopped) {
                return;
            }
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
     * @return bool `true` if the event can be skipped, `false` otherwise.
     */
    public function is_skippable(string $type): bool
    {
        return isset($this->skippable[$type]);
    }

    /**
     * Returns the event hooks attached to the specified event type.
     *
     * @return array<(callable(Event, object|null): void)>
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
     */
    private function search_hook(string $type, callable $hook): string|false
    {
        $hooks = $this->hooks;

        /** @phpstan-ignore-next-line */
        return empty($hooks[$type]) ? false : array_search($hook, $hooks[$type], strict: true);
    }

    /**
     * Consolidate hooks of a same type.
     *
     * If the class of the event's sender is provided, event hooks are filtered according to the class and its
     * hierarchy.
     *
     * @return array<string, (callable(Event, ?object): void)>
     */
    private function consolidate_hooks(string $type): array
    {
        [ $class, $type ] = explode('::', $type);

        $hooks = [];

        while ($class) {
            $k = $class . '::' . $type;

            if (isset($this->hooks[$k])) {
                $hooks = array_merge($hooks, $this->hooks[$k]);
            }

            $class = get_parent_class($class);
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
        if (!is_object($hook) || empty($this->original_hooks[$hook])) {
            return $hook;
        }

        return $this->original_hooks[$hook];
    }

    /**
     * Adds a reference to the original hook.
     *
     * @return Closure
     *     Returns `$wrapper` back.
     */
    private function shadow_original_hook(callable $hook, Closure $wrapper): Closure
    {
        $this->original_hooks[$wrapper] = $hook;

        return $wrapper;
    }
}
