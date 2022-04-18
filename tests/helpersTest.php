<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie;

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use PHPUnit\Framework\TestCase;

use function ICanBoogie\get_events;

class helpersTest extends TestCase
{
	public function test_get_events(): void
	{
		EventCollectionProvider::undefine();
		$events = get_events();
		$this->assertInstanceOf(EventCollection::class, $events);
		$this->assertSame($events, get_events());
	}
}
