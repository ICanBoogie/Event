<?php

namespace ICanBoogie\EventTest\Target;

use ICanBoogie\Event;
use ICanBoogie\EventTest\Target;

class BeforePracticeEvent extends Event
{
	public function __construct(Target $target)
	{
		parent::__construct($target, 'practice:before');
	}
}
