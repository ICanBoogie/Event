<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\EventTest;

use ICanBoogie\Event;

class WithReferencesEvent extends Event
{
	public $a;
	public $b;
	public $c;

	public function __construct($target, &$a, &$b = null, $c = 'default')
	{
		$this->a = &$a;
		$this->b = &$b;
		$this->c = &$c;

		parent::__construct($target, 'with_reference');
	}
}
