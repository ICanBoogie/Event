<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Event;

use ICanBoogie\Event\Config;
use ICanBoogie\Event\ConfigBuilder;
use ICanBoogie\Event\Listen;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Acme\ListenerServiceWithoutSender;
use Test\ICanBoogie\Acme\ListenerServiceWithSender;
use Test\ICanBoogie\Sample\SampleEvent;
use Test\ICanBoogie\Sample\SampleSender;
use Test\ICanBoogie\SetStateHelper;

use function ICanBoogie\Service\ref;

final class ConfigBuilderTest extends TestCase
{
    private Config $config;

    #[Listen]
    public static function sample_listener_without_target(SampleEvent $event)
    {
    }

    #[Listen]
    public static function sample_listener_with_target(SampleSender\ActionEvent $event, SampleSender $sender)
    {
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = (new ConfigBuilder())
            ->attach(SampleEvent::class, [ self::class, 'sample_listener_without_target' ])
            ->attach_to(
                SampleSender::class,
                SampleSender\ActionEvent::class,
                [ self::class, 'sample_listener_with_target' ]
            )
            ->build();
    }

    public function test_build(): void
    {
        $sut = $this->config;
        $expected = new Config([
            SampleEvent::class => [
                [ self::class, 'sample_listener_without_target' ]
            ],
            SampleSender\ActionEvent::for(SampleSender::class) => [
                [ self::class, 'sample_listener_with_target' ]
            ]
        ]);

        $this->assertEquals($expected, $sut);
    }

    public function test_use_attributes(): void
    {
        $actual = (new ConfigBuilder())
            ->use_attributes()
            ->build();

        $expected = new Config([
            SampleSender\BeforeActionEvent::for(SampleSender::class) => [
                ref(ListenerServiceWithSender::class),
            ],
            SampleSender\BeforeActionEvent::class => [
                ref(ListenerServiceWithoutSender::class),
            ],
            SampleEvent::class => [
                [ self::class, 'sample_listener_without_target' ]
            ],
            SampleSender\ActionEvent::for(SampleSender::class) => [
                [ self::class, 'sample_listener_with_target' ]
            ]
        ]);

        $this->assertEquals($expected, $actual);
    }

    public function test_export(): void
    {
        $this->assertEquals($this->config, SetStateHelper::export_import($this->config));
    }
}
