<?php

namespace ICanBoogie;

class helpersTest extends \PHPUnit_Framework_TestCase
{
	public function test_get_events()
	{
		EventCollectionProvider::undefine();
		$events = get_events();
		$this->assertInstanceOf(EventCollection::class, $events);
		$this->assertSame($events, get_events());
	}
}
