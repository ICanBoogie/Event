<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\EventTest;

use Exception;

use function ICanBoogie\emit;

class SampleA
{
    public function __invoke(array $values): array
    {
        if (!$this->validate($values)) {
            throw new Exception("Values validation failed.");
        }

        emit(new BeforeProcessEvent($this, $values));

        return $this->process($values);
    }

    protected function validate(array $values): bool
    {
        $valid = false;

        emit(new ValidateEvent($this, $values, $valid));

        return $valid;
    }

    protected function process(array $values): array
    {
        emit(new ProcessEvent($this, $values));

        return $values;
    }
}
