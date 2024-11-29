<?php

namespace ICanBoogie\Event;

final readonly class Config
{
    /**
     * @param array{ 'listeners': array<string, callable[]> } $an_array
     *
     * @return self
     */
    public static function __set_state(array $an_array): self
    {
        return new self($an_array['listeners']);
    }

    /**
     * @param array<string, callable[]> $listeners
     */
    public function __construct(
        public array $listeners
    ) {
    }
}
