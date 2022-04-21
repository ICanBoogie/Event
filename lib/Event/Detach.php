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

use Closure;
use ICanBoogie\EventCollection;

/**
 * Used to detach an event.
 *
 * ```php
 * <?php
 *
 * use ICanBoogie\HTTP\Dispatcher;
 *
 * $detach = $events->attach(function(Dispatcher\CollectEvent $event, Dispatcher $sender) {
 *
 *     // …
 *
 * });
 *
 * // …
 *
 * $detach();
 * ```
 *
 * @internal
 */
final class Detach
{
	public function __construct(
		private readonly EventCollection $events,
		private readonly string $type,
		private readonly Closure $hook
	) {
	}

	/**
	 * Detaches the event hook from the events.
	 */
	public function __invoke(): void
	{
		$this->events->detach($this->type, $this->hook);
	}
}
