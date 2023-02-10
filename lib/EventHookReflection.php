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

use Closure;
use InvalidArgumentException;
use LogicException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

use function assert;
use function count;
use function explode;
use function get_debug_type;
use function implode;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function spl_object_hash;
use function strpos;

/**
 * Reflection of an event hook.
 *
 * @internal
 */
final class EventHookReflection
{
    /**
     * @var array<string, EventHookReflection>
     *     Where _key_ is a hook key.
     */
    private static array $instances = [];

    /**
     * Creates instance from an event hook.
     *
     * @throws InvalidArgumentException if `$hook` is not a valid event hook.
     * @throws ReflectionException
     */
    public static function from(callable $hook): self
    {
        self::assert_valid($hook);

        $key = self::make_key($hook);

        return self::$instances[$key] ??= new self(self::resolve_reflection($hook));
    }

    /**
     * Makes key from event hook.
     *
     * @throws ReflectionException
     */
    private static function make_key(callable $hook): string
    {
        if (is_array($hook)) {
            return implode('#', $hook);
        }

        if ($hook instanceof Closure) {
            $reflection = new ReflectionFunction($hook);

            return $reflection->getFileName() . '#' . $reflection->getStartLine() . '#' . $reflection->getEndLine();
        }

        if (is_object($hook)) {
            return spl_object_hash($hook);
        }

        assert(is_string($hook));

        return $hook;
    }

    /**
     * Asserts that the event hook is valid.
     *
     * @throws InvalidArgumentException if `$hook` is not a valid event hook.
     */
    public static function assert_valid(mixed $hook): void
    {
        is_callable($hook) or throw new InvalidArgumentException(
            format("The event hook must be a callable, %type given: :hook", [
                'type' => get_debug_type($hook),
                'hook' => $hook
            ])
        );
    }

    /**
     * Asserts that the number of parameters is valid.
     *
     * @param ReflectionParameter[] $parameters
     */
    public static function assert_valid_parameters_number(array $parameters): void
    {
        $n = count($parameters);

        if ($n < 1) {
            throw new LogicException("Expecting at least 1 parameter got none.");
        }

        if ($n > 2) {
            throw new LogicException("Expecting at most 2 parameters got $n.");
        }
    }

    /**
     * Resolves hook reflection.
     *
     * @throws ReflectionException
     */
    private static function resolve_reflection(callable $hook): ReflectionFunctionAbstract
    {
        if (is_object($hook)) {
            return new ReflectionMethod($hook, '__invoke');
        }

        if (is_array($hook)) {
            return new ReflectionMethod($hook[0], $hook[1]);
        }

        if (is_string($hook) && strpos($hook, '::')) {
            [ $class, $method ] = explode('::', $hook);

            return new ReflectionMethod($class, $method);
        }

        assert(is_string($hook) || $hook instanceof Closure);

        return new ReflectionFunction($hook);
    }

    /**
     * Returns the class of a parameter reflection.
     *
     * Contrary of the {@link ReflectionParameter::getClass()} method, the class does not need to
     * be available to be successfully retrieved.
     *
     * @return class-string
     */
    private static function resolve_parameter_class(ReflectionParameter $param): string
    {
        if (!preg_match('/([\w\\\]+)\s\$/', $param, $matches)) {
            throw new LogicException("The parameter `$param->name` is not typed.");
        }

        /** @phpstan-ignore-next-line  */
        return $matches[1]
            ?? throw new LogicException("Unable to resolve class from parameters `$param->name");
    }

    private ReflectionFunctionAbstract $reflection;

    /**
     * @var string The event type resolved from the event hook parameters.
     */
    public readonly string $type;

    private function __construct(ReflectionFunctionAbstract $reflection)
    {
        $this->reflection = $reflection;
        $this->type = $this->resolve_type();
    }

    /**
     * Returns the event type resolved from the event hook parameters.
     */
    private function resolve_type(): string
    {
        $parameters = $this->reflection->getParameters();

        self::assert_valid_parameters_number($parameters);

        [ $event_param, $sender_param ] = $parameters + [ 1 => null ];

        assert($event_param instanceof ReflectionParameter);

        try {
            $event_class = self::resolve_parameter_class($event_param);
        } catch (LogicException $e) {
            throw new LogicException(
                "The parameter `$event_param->name` must be an instance of `ICanBoogie\Event`.",
                previous: $e
            );
        }

        assert(is_subclass_of($event_class, Event::class));

        if (!$sender_param) {
            return $event_class;
        }

        $sender_class = self::resolve_parameter_class($sender_param);

        /** @var Event $event_class */

        return $event_class::for($sender_class);
    }
}
