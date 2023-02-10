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

final class Config
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
        public readonly array $listeners
    ) {
    }
}
