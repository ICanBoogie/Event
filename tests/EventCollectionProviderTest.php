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

class EventCollectionProviderTest extends \PHPUnit_Framework_TestCase
{
	public function test_provider()
	{
		EventCollectionProvider::using(function() {

			static $collection;

			return $collection ?: $collection = new EventCollection;

		});

		$events = EventCollectionProvider::provide();

		$this->assertInstanceOf(EventCollection::class, $events);
		$this->assertSame($events, EventCollectionProvider::provide());
	}

	/**
	 * @expectedException \LogicException
	 */
	public function test_should_throw_exception_when_no_provider()
	{
		EventCollectionProvider::clear();
		EventCollectionProvider::provide();
	}
}
