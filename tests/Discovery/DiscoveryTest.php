<?php

namespace Test\ICanBoogie\Discovery;

use ICanBoogie\Event\ConfigBuilder;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Sample\SampleSender;

use function ICanBoogie\Service\ref;

final class DiscoveryTest extends TestCase
{
    /**
     * @var array<non-empty-string, callable[]>
     */
    private array $listeners;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listeners = new ConfigBuilder()->enable_discovery()->build()->listeners;
    }

    public function test_discovery(): void
    {
        $this->assertEqualsCanonicalizing(
            [
                // Static method, and invokable service, without sender
                ShippedEvent::class => [
                    [ StaticListeners::class, 'on_shipped' ],
                    ref(DispatchService::class),
                ],
                // Static method with a sender class, and invokable service with a custom reference
                ShippedEvent::for(Courier::class) => [
                    [ StaticListeners::class, 'on_shipped_by_courier' ],
                    ref('audit'),
                ],
                // Static method with a sender interface
                ShippedEvent::for(Trackable::class) => [
                    [ StaticListeners::class, 'on_shipped_by_trackable' ],
                ],
                // Invokable services, with and without sender
                SampleSender\BeforeActionEvent::for(SampleSender::class) => [
                    ref(ListenerServiceWithSender::class),
                ],
                SampleSender\BeforeActionEvent::class => [
                    ref(ListenerServiceWithoutSender::class),
                ],
            ],
            $this->listeners
        );
    }

    public function test_discovery_is_disabled_by_default(): void
    {
        $this->assertSame([], new ConfigBuilder()->build()->listeners);
    }

    public function test_disable_discovery(): void
    {
        $config = new ConfigBuilder()
            ->enable_discovery()
            ->disable_discovery()
            ->build();

        $this->assertSame([], $config->listeners);
    }

    public function test_discovered_listeners_follow_the_listeners_attached_explicitly(): void
    {
        $explicit = [ self::class, 'explicit_listener' ];
        $config = new ConfigBuilder()
            ->enable_discovery()
            ->attach(ShippedEvent::class, $explicit)
            ->build();

        $this->assertSame($explicit, $config->listeners[ShippedEvent::class][0]);
        $this->assertCount(3, $config->listeners[ShippedEvent::class]);
    }

    public function test_building_twice_does_not_attach_the_discovered_listeners_twice(): void
    {
        $builder = new ConfigBuilder()->enable_discovery();

        $this->assertEquals($builder->build(), $builder->build());
    }

    public static function explicit_listener(ShippedEvent $event): void
    {
    }
}
