<?php

namespace Test\ICanBoogie\Sample;

class ProcessorExtension extends Processor
{
    protected function process(array $values): array
    {
        return parent::process($values + [ 'five' => 5 ]);
    }
}
