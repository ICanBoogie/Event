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

class SampleB extends SampleA
{
	protected function process(array $values): array
	{
		return parent::process($values + [ 'five' => 5 ]);
	}
}
