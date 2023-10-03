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

use ICanBoogie\Accessor\AccessorTrait;

use function func_num_args;
use function get_called_class;
use function is_object;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * An event.
 *
 * @property-read bool $stopped `true` when the event was stopped, `false` otherwise.
 */
abstract class Event
{
    /**
     * @uses get_stopped
     */
    use AccessorTrait;

    /**
     * @param object|class-string $sender
     *
     * @return string
     *     A qualified event type made of the sender class and the unqualified event type.
     *     e.g. "Exception::recover"
     */
    public static function for(string|object $sender): string
    {
        if (is_object($sender)) {
            $sender = $sender::class;
        }

        return $sender . '::' . get_called_class();
    }

    /**
     * The object the event is dispatched on.
     *
     * **Note:** The property is only initialized if the event is constructed with a sender.
     */
    public readonly object $sender; // @phpstan-ignore-line

    /**
     * Event unqualified type e.g. `MyEvent`.
     */
    public readonly string $unqualified_type;

    /**
     * Event qualified type. e.g. `Exception::MyEvent`
     */
    public readonly string $qualified_type;

    /**
     * `true` when the event was stopped, `false` otherwise.
     */
    private bool $stopped = false;

    /** @phpstan-ignore-next-line */
    private function get_stopped(): bool
    {
        return $this->stopped;
    }

    /**
     * @param object|null $sender The sender of the event.
     */
    public function __construct(object $sender = null)
    {
        if (func_num_args() > 1) {
            trigger_error(
                "The 'type' parameter is no longer supported, the event class is used instead.",
                E_USER_DEPRECATED
            );
        }

        if (func_num_args() > 2) {
            trigger_error(
                "The 'payload' parameter is no longer supported, better write an event class.",
                E_USER_DEPRECATED
            );
        }

        $this->unqualified_type = $this::class;

        if ($sender) {
            $this->sender = $sender;
            $this->qualified_type = static::for($sender);
        } else {
            $this->qualified_type = $this->unqualified_type;
        }
    }

    /**
     * Stops the hooks chain.
     *
     * After the `stop()` method is called the hooks chain is broken and no other hook is called.
     */
    public function stop(): void
    {
        $this->stopped = true;
    }

    /**
     * Chain of hooks to execute once the event has been fired.
     *
     * @var callable[]
     *
     * @internal
     */
    public array $internal_chain = [];

    /**
     * Add an event hook to the finish chain.
     *
     * The finish chain is executed after the event chain was traversed without being stopped.
     *
     * @phpstan-param (callable(Event, ?object): void) $hook
     *
     * @return $this
     */
    public function chain(callable $hook): static
    {
        $this->internal_chain[] = $hook;

        return $this;
    }
}
