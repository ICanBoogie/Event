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
use ICanBoogie\Accessor\AccessorTrait;
use InvalidArgumentException;
use LogicException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

use function basename;
use function count;
use function explode;
use function get_debug_type;
use function ICanBoogie\Event\qualify_type;
use function implode;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function preg_match;
use function spl_object_hash;
use function strpos;
use function strtr;
use function substr;

/**
 * Reflection of an event hook.
 *
 * @property string $type The event type resolved from the event hook parameters.
 *
 * @internal
 */
class EventHookReflection
{
	/**
	 * @uses get_type
	 */
	use AccessorTrait;

	/**
	 * @var array<string, EventHookReflection>
	 *     Where _key_ is a hook key.
	 */
	static private array $instances = [];

	/**
	 * Creates instance from an event hook.
	 *
	 * @throws InvalidArgumentException if `$hook` is not a valid event hook.
	 * @throws ReflectionException
	 */
	static public function from(callable $hook): self
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
	static private function make_key(callable $hook): string
	{
		if (is_array($hook)) {
			return implode('#', $hook);
		}

		if ($hook instanceof Closure) {
			$reflection = new ReflectionFunction($hook);

			return $reflection->getFileName() . '#' . $reflection->getStartLine() . '#' . $reflection->getEndLine();
		}

		if (is_object($hook)) {
			/* @var $hook object */

			return spl_object_hash($hook);
		}

		/* @var $hook string */

		return $hook;
	}

	/**
	 * Asserts that the event hook is valid.
	 *
	 * @throws InvalidArgumentException if `$hook` is not a valid event hook.
	 */
	static public function assert_valid(callable $hook): void
	{
		is_callable($hook) or throw new InvalidArgumentException(
			format
			(
				"The event hook must be a callable, %type given: :hook", [

					'type' => get_debug_type($hook),
					'hook' => $hook

				]
			)
		);
	}

	/**
	 * Asserts that the number of parameters is valid.
	 *
	 * @param ReflectionParameter[] $parameters
	 */
	static public function assert_valid_parameters_number(iterable $parameters): void
	{
		$n = count($parameters);

		if ($n !== 2) {
			throw new LogicException("Invalid number of parameters, expected 2 got $n.");
		}
	}

	/**
	 * Resolves hook reflection.
	 *
	 * @throws ReflectionException
	 */
	static private function resolve_reflection(callable $hook): ReflectionFunctionAbstract
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

		return new ReflectionFunction($hook);
	}

	/**
	 * Returns the class of a parameter reflection.
	 *
	 * Contrary of the {@link ReflectionParameter::getClass()} method, the class does not need to
	 * be available to be successfully retrieved.
	 */
	static private function resolve_parameter_class(ReflectionParameter $param): ?string
	{
		if (!preg_match('/([\w\\\]+)\s\$/', $param, $matches)) {
			throw new LogicException("The parameter `$param->name` is not typed.");
		}

		return $matches[1];
	}

	/**
	 * Resolves event type from its class.
	 *
	 * @param class-string $class
	 */
	static private function resolve_type_from_class(string $class): string
	{
		$base = basename('/' . strtr($class, '\\', '/'));

		$type = substr($base, 0, -5);
		$type = str_starts_with($base, 'Before')
			? hyphenate(substr($type, 6)) . ':before'
			: hyphenate($type);

		return strtr($type, '-', '_');
	}

	private ReflectionFunctionAbstract $reflection;

	/**
	 * Returns the event type resolved from the event hook parameters.
	 */
	private function get_type(): string
	{
		$parameters = $this->reflection->getParameters();

		self::assert_valid_parameters_number($parameters);

		[ $event, $target ] = $parameters;

		return qualify_type(
			self::resolve_type_from_class(self::resolve_parameter_class($event)),
			self::resolve_parameter_class($target)
		);
	}

	private function __construct(ReflectionFunctionAbstract $reflection)
	{
		$this->reflection = $reflection;
	}
}
