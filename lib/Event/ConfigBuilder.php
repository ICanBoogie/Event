<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Event;

use ICanBoogie\Event;
use LogicException;
use olvlvl\ComposerAttributeCollector\Attributes;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

use function class_exists;
use function ICanBoogie\Service\ref;

final class ConfigBuilder
{
    /**
     * @var array<string, callable[]>
     */
    private array $listeners = [];

    public function build(): Config
    {
        return new Config($this->listeners);
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

    public function use_attributes(): self
    {
        $targets = Attributes::findTargetMethods(Listen::class);

        foreach ($targets as $target) {
            $method = new ReflectionMethod($target->class, $target->name);

            if ($method->isStatic()) {
                /** @var array{ class-string, non-empty-string } $listener */
                $listener = [ $target->class, $target->name ];
            } else {
                if ($target->name !== '__invoke') {
                    throw new LogicException("Only invokable classes can be attached");
                }

                /* @var callable $listener **/
                $listener = ref($target->attribute->ref ?? $target->class);
            }

            $parameters = $method->getParameters();
            $event_class = self::ensure_extends_event($parameters[0]->getType());

            /** @var class-string<Event> $event_class */

            match (count($parameters)) {
                1 => $this->attach($event_class, $listener),
                2 => $this->attach_to(
                    self::ensure_class($parameters[1]->getType()),
                    $event_class,
                    $listener
                ),
                default => throw new LogicException("Too many parameters for $target->class::$target->name")
            };
        }

        return $this;
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

        class_exists($type, true)
            or throw new LogicException("$type is not a loadable class");

        return $type;
    }
}
