<?php

namespace ICanBoogie\Event;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Listen
{
    /**
     * @param non-empty-string|null $ref
     *     The reference of the service.
     *     If the method is non-static, defaults to the class.
     */
    public function __construct(
        public readonly ?string $ref = null,
    ) {
    }
}
