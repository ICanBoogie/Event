<?php

namespace Test\ICanBoogie;

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use PHPUnit\Framework\TestCase;

use function ICanBoogie\get_events;

final class HelpersTest extends TestCase
{
    public function test_get_events(): void
    {
        EventCollectionProvider::undefine();
        $events = get_events();
        $this->assertInstanceOf(EventCollection::class, $events);
        $this->assertSame($events, get_events());
    }
}
