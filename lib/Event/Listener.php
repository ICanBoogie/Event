<?php

namespace ICanBoogie\Event;

use Attribute;

/**
 * Marks a method as an event listener.
 *
 * The event type is inferred from the first parameter of the method. An optional second parameter
 * gives the type of the sender (class or interface) the listener is attached to. Static methods are
 * attached as `[class, method]`; non-static methods must be `__invoke()` and are resolved as
 * services, see {@see self::$ref}.
 *
 * Marked methods are collected by {@see ConfigBuilder::use_attributes()}.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Listener
{
    /**
     * @param non-empty-string|null $ref
     *     The reference of the service.
     *     If the method is non-static, defaults to the class.
     */
    public function __construct(
        public ?string $ref = null,
    ) {
    }
}
