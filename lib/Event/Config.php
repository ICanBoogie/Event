<?php

namespace ICanBoogie\Event;

final readonly class Config
{
    /**
     * @param array{ 'listeners': array<non-empty-string, callable[]> } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self($an_array['listeners']);
    }

    /**
     * @param array<non-empty-string, callable[]> $listeners
     */
    public function __construct(
        public array $listeners
    ) {
    }
}
