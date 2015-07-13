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
 * An event hook.
 *
 * An {@link EventHook} instance is created when an event hook is attached. The purpose of this
 * instance is to ease its detaching:
 *
 * <pre>
 * <?php
 *
 * use ICanBoogie\HTTP\Dispatcher;
 *
 * $eh = $events->attach(function(Dispatcher\CollectEvent $event, Dispatcher $target) {
 *
 *     // …
 *
 * });
 *
 * $eh->detach();
 * </pre>
 *
 * @property-read Events $events Events collection.
 * @property-read string $type Event type
 * @property-read callable $hook Event hook.
 */
class EventHook
{
	use AccessorTrait;

	/**
	 * Event type.
	 *
	 * @var string
	 */
	private $type;

	protected function get_type()
	{
		return $this->type;
	}

	/**
	 * @var callable
	 */
	private $hook;

	protected function get_hook()
	{
		return $this->hook;
	}

	/**
	 * @var Events
	 */
	private $events;

	protected function get_events()
	{
		return $this->events;
	}

	/**
	 * @param Events $events
	 * @param string $type
	 * @param callable $hook
	 */
	public function __construct(Events $events, $type, $hook)
	{
		$this->events = $events;
		$this->type = $type;
		$this->hook = $hook;
	}

	/**
	 * Detaches the event hook from the events.
	 */
	public function detach()
	{
		$this->events->detach($this->type, $this->hook);
	}
}
