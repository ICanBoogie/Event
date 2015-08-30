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

/**
 * Reflection of an event hook.
 *
 * @property \ReflectionFunction|\ReflectionMethod $reflection
 * @property string $type The event type resolved from the event hook parameters.
 */
class EventHookReflection
{
	use AccessorTrait;

	/**
	 * @var EventHookReflection[]
	 */
	static private $instances = [];

	/**
	 * Creates instance from an event hook.
	 *
	 * @param callable $hook
	 *
	 * @return EventHookReflection
	 *
	 * @throws \InvalidArgumentException if `$hook` is not a valid event hook.
	 */
	static public function from($hook)
	{
		self::assert_valid($hook);

		$key = self::make_key($hook);

		if (isset(self::$instances[$key]))
		{
			return self::$instances[$key];
		}

		return self::$instances[$key] = new self(self::resolve_reflection($hook));
	}

	/**
	 * Makes key from event hook.
	 *
	 * @param callable $hook
	 *
	 * @return string
	 */
	static private function make_key($hook)
	{
		if (is_array($hook))
		{
			return implode('#', $hook);
		}

		if ($hook instanceof \Closure)
		{
			$reflection = new \ReflectionFunction($hook);

			return $reflection->getFileName() . '#'. $reflection->getStartLine() . '#'. $reflection->getEndLine();
		}

		if (is_object($hook))
		{
			/* @var $hook object */

			return spl_object_hash($hook);
		}

		/* @var $hook string */

		return $hook;
	}

	/**
	 * Asserts that the event hook is valid.
	 *
	 * @param callable $hook
	 *
	 * @throws \InvalidArgumentException if `$hook` is not a valid event hook.
	 */
	static public function assert_valid($hook)
	{
		if (!is_callable($hook))
		{
			throw new \InvalidArgumentException(format
			(
				'The event hook must be a callable, %type given: :hook', [

					'type' => gettype($hook),
					'hook' => $hook

				]
			));
		}
	}

	/**
	 * Asserts that the number of parameters is valid.
	 *
	 * @param \ReflectionParameter[] $parameters
	 */
	static public function assert_valid_parameters_number(array $parameters)
	{
		$n = count($parameters);

		if ($n !== 2)
		{
			throw new \LogicException("Invalid number of parameters, expected 2 got $n.");
		}
	}

	/**
	 * Resolves hook reflection.
	 *
	 * @param callable $hook
	 *
	 * @return \ReflectionFunction|\ReflectionMethod
	 */
	static private function resolve_reflection($hook)
	{
		if (is_object($hook))
		{
			return new \ReflectionMethod($hook, '__invoke');
		}

		if (is_array($hook))
		{
			return new \ReflectionMethod($hook[0], $hook[1]);
		}

		if (is_string($hook) && strpos($hook, '::'))
		{
			list($class, $method) = explode('::', $hook);

			return new \ReflectionMethod($class, $method);
		}

		return new \ReflectionFunction($hook);
	}

	/**
	 * Returns the class of a parameter reflection.
	 *
	 * Contrary of the {@link ReflectionParameter::getClass()} method, the class does not need to
	 * be available to be successfully retrieved.
	 *
	 * @param \ReflectionParameter $param
	 *
	 * @return string|null
	 */
	static private function resolve_parameter_class(\ReflectionParameter $param)
	{
		if (!preg_match('/([\w\\\]+)\s\$/', $param, $matches))
		{
			throw new \LogicException("The parameter `{$param->name}` is not typed.");
		}

		return $matches[1];
	}

	/**
	 * Resolves event type from its class.
	 *
	 * @param string $class
	 *
	 * @return string
	 */
	static private function resolve_type_from_class($class)
	{
		$base = basename('/' . strtr($class, '\\', '/'));

		$type = substr($base, 0, -5);
		$type = strpos($base, 'Before') === 0
			? hyphenate(substr($type, 6)) . ':before'
			: hyphenate($type);

		return strtr($type, '-', '_');
	}

	private $reflection;

	/**
	 * Returns the event type resolved from the event hook parameters.
	 *
	 * @return string
	 */
	protected function get_type()
	{
		$parameters = $this->reflection->getParameters();

		self::assert_valid_parameters_number($parameters);

		list($event, $target) = $parameters;

		return self::resolve_parameter_class($target) . '::' . self::resolve_type_from_class(self::resolve_parameter_class($event));
	}

	private function __construct($reflection)
	{
		$this->reflection = $reflection;
	}
}
