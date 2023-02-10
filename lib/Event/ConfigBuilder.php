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

use ICanBoogie\Event;

final class ConfigBuilder
{
    /**
     * @var array<string, callable[]>
     */
    private array $listeners = [];

    public function build(): Config
    {
        return new Config($this->listeners);
    }

    /**
     * @param class-string<Event> $event_class
     * @param callable $listener
     *
     * @return $this
     */
    public function attach(string $event_class, callable $listener): self
    {
        $this->listeners[$event_class][] = $listener;

        return $this;
    }

    /**
     * @param class-string $sender_class
     * @param class-string<Event> $event_class
     * @param callable $listener
     *
     * @return $this
     */
    public function attach_to(string $sender_class, string $event_class, callable $listener): self
    {
        $this->listeners[$event_class::for($sender_class)][] = $listener;

        return $this;
    }
}
