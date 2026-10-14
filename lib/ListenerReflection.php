<?php

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
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function spl_object_hash;
use function strpos;

/**
 * Reflection of an event listener.
 *
 * @internal
 */
final class ListenerReflection
{
    /**
     * @var array<string, ListenerReflection>
     *     Where _key_ is a listener key.
     */
    private static array $instances = [];

    /**
     * Creates a {@see ListenerReflection} from an event listener.
     *
     * @throws InvalidArgumentException if `$listener` is not a valid event listener.
     * @throws ReflectionException
     */
    public static function from(callable $listener): self
    {
        self::assert_valid($listener);

        $key = self::make_key($listener);

        return self::$instances[$key] ??= new self(self::resolve_reflection($listener));
    }

    /**
     * Makes key from event listener.
     *
     * @throws ReflectionException
     */
    private static function make_key(callable $listener): string
    {
        if (is_array($listener)) {
            [ $target, $method ] = $listener;

            return (is_object($target) ? spl_object_hash($target) : $target) . '#' . $method;
        }

        if ($listener instanceof Closure) {
            $reflection = new ReflectionFunction($listener);

            return $reflection->getFileName() . '#' . $reflection->getStartLine() . '#' . $reflection->getEndLine();
        }

        if (is_object($listener)) {
            return spl_object_hash($listener);
        }

        assert(is_string($listener));

        return $listener;
    }

    /**
     * Asserts that the event listener is valid.
     *
     * @throws InvalidArgumentException if `$listener` is not a valid event listener.
     */
    public static function assert_valid(mixed $listener): void
    {
        is_callable($listener)
        or throw new InvalidArgumentException(
            "The event listener must be a callable, given: " . get_debug_type($listener),
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
     * Resolves listener reflection.
     *
     * @throws ReflectionException
     */
    private static function resolve_reflection(callable $listener): ReflectionFunctionAbstract
    {
        if (is_object($listener)) {
            return new ReflectionMethod($listener, '__invoke');
        }

        if (is_array($listener)) {
            return new ReflectionMethod($listener[0], $listener[1]);
        }

        if (is_string($listener) && strpos($listener, '::')) {
            [ $class, $method ] = explode('::', $listener);

            return new ReflectionMethod($class, $method);
        }

        assert(is_string($listener) || $listener instanceof Closure);

        return new ReflectionFunction($listener);
    }

    /**
     * Returns the class of a parameter reflection.
     *
     * Contrary of the {@see ReflectionParameter::getClass()} method, the class doesn't need to
     * be available to be successfully retrieved.
     *
     * @return class-string
     */
    private static function resolve_parameter_class(ReflectionParameter $param): string
    {
        if (!preg_match('/([\w\\\]+)\s\$/', $param, $matches)) {
            throw new LogicException("The parameter `$param->name` is not typed");
        }

        /** @phpstan-ignore-next-line */
        return $matches[1]
            ?? throw new LogicException("Unable to resolve class from parameters `$param->name");
    }

    private ReflectionFunctionAbstract $reflection;

    /**
     * @var non-empty-string The event type resolved from the event listener parameters.
     */
    public readonly string $type;

    private function __construct(ReflectionFunctionAbstract $reflection)
    {
        $this->reflection = $reflection;
        $this->type = $this->resolve_type();
    }

    /**
     * Returns the event type resolved from the event listener parameters.
     *
     * @return non-empty-string
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
                "The parameter `$event_param->name` must be an instance of ICanBoogie\Event",
                previous: $e,
            );
        }

        assert(is_subclass_of($event_class, Event::class));
        /** @var class-string<Event> $event_class */

        if (!$sender_param) {
            return $event_class;
        }

        $sender_class = self::resolve_parameter_class($sender_param);

        return $event_class::for($sender_class);
    }
}
