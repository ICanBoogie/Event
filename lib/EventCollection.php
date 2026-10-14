<?php

namespace ICanBoogie;

use ArrayIterator;
use Closure;
use Generator;
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
use function class_implements;
use function count;
use function explode;
use function get_parent_class;
use function is_callable;
use function is_object;
use function microtime;
use function strpos;

/**
 * Events collected from the "listeners" config or attached by the user.
 *
 * @implements IteratorAggregate<non-empty-string, (callable(Event, ?object): void)[]>
 */
class EventCollection implements IteratorAggregate
{
    /**
     * Resolves type and listener.
     *
     * Listeners are accepted whatever the event they are meant for, hence `never` for their parameters. They
     * are returned as called by the collection: with any event and sender, but they only ever receive the
     * ones they were attached for, which is the type resolved here.
     *
     * @param (callable(never, never): void)|(callable(never): void)|non-empty-string $type_or_listener
     * @param (callable(never, never): void)|(callable(never): void)|null $listener
     *
     * @return array{ non-empty-string, (callable(Event, ?object): void) } Returns the type and listener.
     *
     * @throws ReflectionException
     */
    private static function resolve_type_and_listener(string|callable $type_or_listener, ?callable $listener): array
    {
        if ($listener === null) {
            $type = null;
            $listener = $type_or_listener;
        } else {
            $type = $type_or_listener;
        }

        ListenerReflection::assert_valid($listener);

        assert(is_callable($listener));

        if ($type === null) {
            $type = ListenerReflection::from($listener)->type;
        }

        assert(is_string($type));

        // @phpstan-ignore return.type (see the description of the method)
        return [ $type, $listener ];
    }

    /**
     * Event listeners by type.
     *
     * @var array<non-empty-string, (callable(Event, ?object): void)[]>
     *     Where _key_ is an event type and _value_ an array of callables.
     */
    private array $listeners = [];

    /**
     * Event listeners by class and type.
     *
     * @var array<string, (callable(Event, ?object): void)[]>
     *     Where _key_ is an event type and _value_ an array of callables.
     */
    private array $consolidated_listeners = [];

    /**
     * @var SplObjectStorage<Closure, callable>
     */
    private SplObjectStorage $original_listeners;

    /**
     * Skippable events.
     *
     * @var array<string, true>
     *     Where _key_ is an event type.
     */
    private array $skippable = [];

    public function __construct(?Config $config = null)
    {
        $this->original_listeners = new SplObjectStorage();

        if ($config) {
            $this->attach_many($config);
        }
    }

    /**
     * Returns an iterator for event listeners.
     *
     * @return Generator<non-empty-string, (callable(Event, ?object): void)[]>
     */
    public function getIterator(): Generator
    {
        yield from $this->listeners;
    }

    /**
     * Revokes consolidated listeners and skippable types.
     */
    private function revoke_traces(): void
    {
        $this->consolidated_listeners = [];
        $this->skippable = [];
    }

    /**
     * Attaches an event listener.
     *
     * @template TEvent of Event
     * @template TSender of ?object
     *
     * @param (Closure(TEvent, TSender): void)|(Closure(TEvent): void)|non-empty-string $type_or_listener
     *     Event type or event listener.
     * @param (Closure(TEvent, TSender): void)|(Closure(TEvent): void)|null $listener
     *     The event listener, or nothing if $type is the event listener.
     *
     * @throws ReflectionException
     */
    public function attach(Closure|string $type_or_listener, ?Closure $listener = null): Detach
    {
        [ $type, $listener ] = self::resolve_type_and_listener($type_or_listener, $listener);

        assert($listener instanceof Closure);

        $this->listeners[$type] ??= [];
        array_unshift($this->listeners[$type], $listener);

        // Listeners attached to a bare type also apply to the events with a sender, hence the consolidated listeners
        // are revoked whatever the type.
        $this->revoke_traces();

        return new Detach($this, $type, $listener);
    }

    /**
     * Attaches many event listeners at once.
     *
     * **Note**: The event listeners must be grouped by event type.
     */
    public function attach_many(Config $config): void
    {
        $listeners = &$this->listeners;
        $intersect = array_intersect_key($config->listeners, $listeners);
        $listeners += array_diff_key($config->listeners, $listeners);

        foreach ($intersect as $type => $type_listeners) {
            $listeners[$type] = array_merge($listeners[$type], $type_listeners);
        }

        $listeners = array_map(function ($callables) {
            return array_values(array_unique($callables, SORT_REGULAR));
        }, $listeners);

        $this->revoke_traces();
    }

    /**
     * Attaches an event listener to a specific sender.
     *
     * @template TEvent of Event
     * @template TSender of object
     *
     * @param TSender $sender
     * @param Closure(TEvent, TSender): void $listener
     *
     * @throws ReflectionException
     */
    public function attach_to(object $sender, Closure $listener): Detach
    {
        $type = ListenerReflection::from($listener)->type;

        return $this->attach(
            $type,
            $this->shadow_original_listener($listener, static function (Event $e, ?object $s) use ($sender, $listener): void {
                if ($s !== $sender) {
                    return;
                }

                // @phpstan-ignore argument.type (the type of the listener is the type of the event it is attached to)
                $listener($e, $s);
            })
        );
    }

    /**
     * Attaches an event listener that is detached once used.
     *
     * @template TEvent of Event
     * @template TSender of ?object
     *
     * @param (Closure(TEvent, TSender): void)|(Closure(TEvent): void)|non-empty-string $type_or_listener
     *     Event type or event listener.
     * @param (Closure(TEvent, TSender): void)|(Closure(TEvent): void)|null $listener
     *     The event listener, or nothing if $type is the event listener.
     *
     * @throws ReflectionException
     */
    public function once(Closure|string $type_or_listener, ?Closure $listener = null): Detach
    {
        [ $type, $listener ] = self::resolve_type_and_listener($type_or_listener, $listener);

        $detach = $this->attach(
            $type,
            $this->shadow_original_listener($listener, static function (Event $e, ?object $s) use ($listener, &$detach): void {
                $listener($e, $s);

                /** @phpstan-ignore-next-line */
                $detach();
            })
        );

        return $detach;
    }

    /**
     * Detaches an event listener.
     *
     * @param non-empty-string $type The name of the event.
     * @param callable $listener The event listener.
     *
     * @throws LogicException when the event listener is not attached to the event name.
     */
    public function detach(string $type, callable $listener): void
    {
        $listeners = &$this->listeners;
        $key = $this->search_listener($type, $listener);

        if ($key === false) {
            throw new LogicException("The specified event listener is not attached to `$type`.");
        }

        unset($listeners[$type][$key]);

        if (!count($listeners[$type])) {
            unset($listeners[$type]);
        }

        $this->consolidated_listeners = [];
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

        $listeners = $this->get_listeners($type);

        if (!$listeners) {
            EventProfiler::add_unused($type);

            $this->skip($type);

            return $event;
        }

        $this->process_chain($event, $listeners, $type, $sender);

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
     * @param non-empty-string $type
     */
    private function process_chain(Event $event, iterable $chain, string $type, ?object $sender): void
    {
        foreach ($chain as $listener) {
            $started_at = microtime(true);

            try {
                $listener($event, $sender);
            } finally {
                EventProfiler::add_call($type, $this->resolve_original_listener($listener), $started_at);
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
     * Returns whether an event has been marked as skippable.
     *
     * @param string $type The event type.
     */
    public function is_skippable(string $type): bool
    {
        return isset($this->skippable[$type]);
    }

    /**
     * Returns the event listeners attached to the specified event type.
     *
     * @return array<(callable(Event, object|null): void)>
     */
    public function get_listeners(string $type): array
    {
        if (!strpos($type, '::')) {
            return $this->listeners[$type] ?? [];
        }

        if (isset($this->consolidated_listeners[$type])) {
            return $this->consolidated_listeners[$type];
        }

        return $this->consolidated_listeners[$type] = $this->consolidate_listeners($type);
    }

    /**
     * Searches an event listener.
     */
    private function search_listener(string $type, callable $listener): string|false
    {
        $listeners = $this->listeners;

        /** @phpstan-ignore-next-line */
        return empty($listeners[$type]) ? false : array_search($listener, $listeners[$type], strict: true);
    }

    /**
     * Consolidate listeners of the same type.
     *
     * If the class of the event's sender is provided, event listeners are filtered according to the class and its
     * hierarchy: listeners attached to the class come first, followed by the listeners attached to its parent classes, the
     * listeners attached to the interfaces it implements, e.g. `Throwable`, and finally the listeners attached to the bare
     * event type, which are called whatever the sender.
     *
     * @return (callable(Event, ?object): void)[]
     */
    private function consolidate_listeners(string $type): array
    {
        [ $sender_class, $type ] = explode('::', $type);

        $listeners = [];
        $class = $sender_class;

        while ($class) {
            $k = $class . '::' . $type;

            if (isset($this->listeners[$k])) {
                $listeners = array_merge($listeners, $this->listeners[$k]);
            }

            $class = get_parent_class($class);
        }

        foreach (class_implements($sender_class) ?: [] as $interface) {
            $k = $interface . '::' . $type;

            if (isset($this->listeners[$k])) {
                $listeners = array_merge($listeners, $this->listeners[$k]);
            }
        }

        if (isset($this->listeners[$type])) {
            $listeners = array_merge($listeners, $this->listeners[$type]);
        }

        return $listeners;
    }

    private function resolve_original_listener(callable $listener): callable
    {
        if (!is_object($listener) || empty($this->original_listeners[$listener])) {
            return $listener;
        }

        return $this->original_listeners[$listener];
    }

    /**
     * Adds a reference to the original listener.
     *
     * @return Closure
     *     Returns `$wrapper` back.
     */
    private function shadow_original_listener(callable $listener, Closure $wrapper): Closure
    {
        $this->original_listeners[$wrapper] = $listener;

        return $wrapper;
    }
}
