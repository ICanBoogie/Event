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

use PHPUnit\Framework\TestCase;

class EventCollectionProviderTest extends TestCase
{
	public function test_provider()
	{
		$provider = function() {

			static $collection;

			return $collection ?: $collection = new EventCollection;

		};

		EventCollectionProvider::define($provider);

		$this->assertSame($provider, EventCollectionProvider::defined());

		$events = EventCollectionProvider::provide();

		$this->assertInstanceOf(EventCollection::class, $events);
		$this->assertSame($events, EventCollectionProvider::provide());
	}

	public function test_should_throw_exception_when_no_provider()
	{
		EventCollectionProvider::define(function() {});
		EventCollectionProvider::undefine();
		$this->assertNull(EventCollectionProvider::defined());
		$this->expectException(\LogicException::class);
		EventCollectionProvider::provide();
	}
}
