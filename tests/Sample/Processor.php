<?php

namespace Test\ICanBoogie\Sample;

use Exception;
use Test\ICanBoogie\Sample\Processor\BeforeProcessEvent;
use Test\ICanBoogie\Sample\Processor\ProcessEvent;
use Test\ICanBoogie\Sample\Processor\ValidateEvent;

use function ICanBoogie\emit;

class Processor
{
    /**
     * @param mixed[] $values
     *
     * @return mixed[]
     */
    public function __invoke(array $values): array
    {
        if (!$this->validate($values)) {
            throw new Exception("Values validation failed.");
        }

        emit(new BeforeProcessEvent($this, $values));

        return $this->process($values);
    }

    /**
     * @param mixed[] $values
     */
    protected function validate(array $values): bool
    {
        $valid = false;

        emit(new ValidateEvent($this, $values, $valid));

        return $valid;
    }

    /**
     * @param mixed[] $values
     *
     * @return mixed[]
     */
    protected function process(array $values): array
    {
        emit(new ProcessEvent($this, $values));

        return $values;
    }
}
