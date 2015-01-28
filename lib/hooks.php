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

class Hooks
{
	/**
	 * Synthesizes a configuration suitable to create {@link Events} instances, from the "hooks"
	 * config.
	 *
	 * @param array $fragments Configuration fragments.
	 *
	 * @throws \InvalidArgumentException in attempt to specify an invalid event callback.
	 *
	 * @return array
	 */
	static public function synthesize_config(array $fragments)
	{
		$events = [];

		foreach ($fragments as $pathname => $fragment)
		{
			if (empty($fragment['events']))
			{
				continue;
			}

			foreach ($fragment['events'] as $type => $callback)
			{
				if (!is_callable($callback, true))
				{
					throw new \InvalidArgumentException(\ICanBoogie\format
					(
						'Event callback must be a string, %type given: :callback in %path.', [

							'type' => gettype($callback),
							'callback' => $callback,
							'path' => $pathname

						]
					));
				}

				#
				# because modules are ordered by weight (most important are first), we can
				# push callbacks instead of unshifting them.
				#

				$events[$type][] = $callback;
			}
		}

		return $events;
	}

	/**
	 * Returns an {@link Events} instance created with the hooks from the `events` config.
	 *
	 * @param \ICanBoogie\Core $core
	 *
	 * @return \ICanBoogie\Events
	 */
	static public function core_lazy_get_events(\ICanBoogie\Core $core)
	{
		return new \ICanBoogie\Events($core->configs['events']);
	}
}
