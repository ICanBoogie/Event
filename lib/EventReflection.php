<?php

namespace ICanBoogie;

/**
 * Creates unfired, initialized instance of events.
 */
class EventReflection
{
	static private $instances = [];

	/**
	 * Returns the {@link EventReflection} instance associated with the specified event class.
	 *
	 * @param string $class
	 *
	 * @return EventReflection
	 */
	static public function from($class)
	{
		if (isset(self::$instances[$class]))
		{
			return self::$instances[$class];
		}

		return self::$instances[$class] = new static($class);
	}

	/*
	 * Instance
	 */

	protected $class;
	protected $parameters;

	protected function __construct($class)
	{
		$construct_reflection = new \ReflectionMethod($class, '__construct');
		$parameters_reflection = $construct_reflection->getParameters();

		$parameters = [];

		foreach ($parameters_reflection as $parameter)
		{
			$parameters[$parameter->name] = $parameter;
		}

		$this->class = new \ReflectionClass($class);
		$this->parameters = $parameters;
	}

	/**
	 * Makes unfired, initialized event instance.
	 *
	 * @param array $params
	 *
	 * @return Event
	 */
	public function with(array $params)
	{
		$this->assert_no_extraneous($params);
		$this->assert_no_missing($params);
		$this->assert_no_skipped($params);

		$event = $this->make_instance();

		$event->__construct(...$this->make_args($params));

		return $event;
	}

	/**
	 * Asserts that no extraneous parameter is specified.
	 *
	 * @param array $params
	 *
	 * @throws \BadMethodCallException when an extraneous parameter is specified.
	 */
	protected function assert_no_extraneous(array $params)
	{
		$extraneous = array_diff_key($params, $this->parameters);

		if ($extraneous)
		{
			throw new \BadMethodCallException("The following parameters are extraneous: " . implode(', ', array_keys($extraneous)) . ".");
		}
	}

	/**
	 * Asserts that no required parameter is missing.
	 *
	 * @param array $params
	 *
	 * @throws \BadMethodCallException when a required parameter is missing.
	 */
	protected function assert_no_missing(array $params)
	{
		$missing = [];

		/* @var $reflection \ReflectionParameter */

		foreach (array_diff_key($this->parameters, $params) as $param => $reflection)
		{
			if ($reflection->isDefaultValueAvailable())
			{
				continue;
			}

			$missing[$param] = $reflection;
		}

		if ($missing)
		{
			throw new \BadMethodCallException("The following parameters are required: " . implode(', ', array_keys($missing)) . ".");
		}
	}

	/**
	 * Asserts that no parameter is skipped.
	 *
	 * @param array $params
	 *
	 * @throws \BadMethodCallException when a parameter is skipped.
	 */
	protected function assert_no_skipped(array $params)
	{
		$skipped = array_diff_key(array_slice($this->parameters, 0, count($params)), $params);

		if ($skipped)
		{
			throw new \BadMethodCallException("The following parameters are skipped: " . implode(', ', array_keys($skipped)) . ".");
		}
	}

	/**
	 * Makes event instance.
	 *
	 * @return Event
	 */
	protected function make_instance()
	{
		static $no_immediate_fire;

		if (!$no_immediate_fire)
		{
			$no_immediate_fire = new \ReflectionProperty(Event::class, 'no_immediate_fire');
			$no_immediate_fire->setAccessible(true);
		}

		/* @var $event Event */

		$event = $this->class->newInstanceWithoutConstructor();
		$no_immediate_fire->setValue($event, true);

		return $event;
	}

	/**
	 * Makes construct arguments.
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	protected function make_args(array $params)
	{
		$args = [];

		foreach(array_keys($this->parameters) as $param)
		{
			if (!array_key_exists($param, $params))
			{
				break;
			}

			$args[] = &$params[$param];
		}

		return $args;
	}
}
