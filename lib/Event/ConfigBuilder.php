<?php

namespace ICanBoogie\Event;

use ICanBoogie\Config\Builder;
use ICanBoogie\Event;
use LogicException;
use olvlvl\ComposerAttributeCollector\Attributes;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

use function class_exists;
use function interface_exists;
use function ICanBoogie\Service\ref;

/**
 * Builds a {@see Config} of event listeners.
 *
 * Listeners can be attached explicitly with {@see attach()} and {@see attach_to()}, or discovered
 * with {@see enable_discovery()}. Listeners are callables, to reference a service use
 * `ICanBoogie\Service\ref()`. The resulting {@see Config} can be given to the `EventCollection`
 * constructor or to `attach_many()`.
 *
 * ```php
 * $config = (new ConfigBuilder())
 *     ->attach(MyEvent::class, $listener)
 *     ->attach_to(RuntimeException::class, RecoverEvent::class, $listener)
 *     ->enable_discovery()
 *     ->build();
 * ```
 *
 * @implements Builder<Config>
 */
final class ConfigBuilder implements Builder
{
    public static function get_fragment_filename(): string
    {
        return 'event';
    }

    /**
     * @var array<non-empty-string, callable[]>
     */
    private array $listeners = [];

    private bool $discovery = false;

    /**
     * @throws LogicException if discovery is enabled and the listeners found are not valid, or the attribute
     * collector is not available.
     */
    public function build(): Config
    {
        if (!$this->discovery) {
            return new Config($this->listeners);
        }

        // Discovery works on a copy, so that building twice doesn't attach the discovered listeners twice.
        $builder = clone $this;
        $builder->discover_listeners();

        return new Config($builder->listeners);
    }

    /**
     * @param class-string<Event> $event_class
     * @param callable $listener
     *
     * @return $this
     */
    public function attach(string $event_class, callable $listener): self
    {
        $this->listeners[$event_class][] = $listener;

        return $this;
    }

    /**
     * @param class-string $sender_class
     * @param class-string<Event> $event_class
     * @param callable $listener
     *
     * @return $this
     */
    public function attach_to(string $sender_class, string $event_class, callable $listener): self
    {
        $this->listeners[$event_class::for($sender_class)][] = $listener;

        return $this;
    }

    /**
     * Enables the discovery of the methods marked with the {@see Listener} attribute, they are attached after the
     * listeners attached explicitly, when {@see build()} is invoked.
     *
     * Discovery requires `olvlvl/composer-attribute-collector`.
     *
     * @return $this
     */
    public function enable_discovery(): self
    {
        $this->discovery = true;

        return $this;
    }

    /**
     * Disables the discovery of the listeners, it is disabled by default.
     *
     * @return $this
     */
    public function disable_discovery(): self
    {
        $this->discovery = false;

        return $this;
    }

    /**
     * Attaches the methods marked with the {@see Listener} attribute.
     *
     * @throws LogicException if the attribute collector is not available, a non-static method is not `__invoke()`,
     * a method has more than two parameters, or the parameter types are not suitable (the first must be an
     * {@see Event}, the second a loadable class or interface).
     */
    private function discover_listeners(): void
    {
        class_exists(Attributes::class)
        or throw new LogicException("Discovery requires `olvlvl/composer-attribute-collector`");

        $targets = Attributes::findTargetMethods(Listener::class);

        foreach ($targets as $target) {
            $method = new ReflectionMethod($target->class, $target->name);

            if ($method->isStatic()) {
                /** @var array{ class-string, non-empty-string } $listener */
                $listener = [ $target->class, $target->name ];
            } else {
                if ($target->name !== '__invoke') {
                    throw new LogicException("Only invokable classes can be attached");
                }

                /* @var callable $listener * */
                $listener = ref($target->attribute->ref ?? $target->class);
            }

            $parameters = $method->getParameters();
            $event_class = self::ensure_extends_event($parameters[0]->getType());

            /** @var class-string<Event> $event_class */

            match (count($parameters)) {
                1 => $this->attach($event_class, $listener), // @phpstan-ignore-line
                2 => $this->attach_to(
                    self::ensure_class($parameters[1]->getType()),
                    $event_class,
                    $listener, // @phpstan-ignore-line
                ),
                default => throw new LogicException("Too many parameters for $target->class::$target->name")
            };
        }
    }

    /**
     * @return class-string<Event>
     */
    private static function ensure_extends_event(?ReflectionType $type): string
    {
        $type instanceof ReflectionNamedType
        or throw new LogicException("Expected named type, got: $type");

        $type = $type->getName();

        is_a($type, Event::class, true)
        or throw new LogicException("$type does not extend " . Event::class);

        return $type;
    }

    /**
     * @return class-string
     */
    private static function ensure_class(?ReflectionType $type): string
    {
        $type instanceof ReflectionNamedType
        or throw new LogicException("Expected named type, got: $type");

        $type = $type->getName();

        class_exists($type) || interface_exists($type)
        or throw new LogicException("$type is not a loadable class or interface");

        return $type;
    }
}
