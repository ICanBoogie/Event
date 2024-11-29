<?php

namespace ICanBoogie\Event;

use Attribute;

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
