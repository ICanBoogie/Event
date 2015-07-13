<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use ICanBoogie\EventTest\WithReferencesEvent;

class EventReflectionTest extends \PHPUnit_Framework_TestCase
{
	public function test_make_should_throw_exception_on_extraneous_param()
	{
		$target = new \StdClass;
		$a = null;
		$extra = 'extra' . uniqid();

		try
		{
			EventReflection::from(WithReferencesEvent::class)->with([

				'target' => $target,
				'a' => &$a,
				$extra => uniqid()

			]);

			$this->fail("Expected exception.");
		}
		catch (\Exception $e)
		{
			$this->assertInstanceOf(\BadMethodCallException::class, $e);
			$this->assertContains('extraneous', $e->getMessage());
			$this->assertContains($extra, $e->getMessage());
		}
	}

	public function test_make_should_throw_exception_on_missing_param()
	{
		$target = new \StdClass;

		try
		{
			EventReflection::from(WithReferencesEvent::class)->with([

				'target' => $target

			]);

			$this->fail("Expected exception.");
		}
		catch (\Exception $e)
		{
			$this->assertInstanceOf(\BadMethodCallException::class, $e);
			$this->assertContains('required', $e->getMessage());
		}
	}

	public function test_make_should_throw_exception_on_skipped_param()
	{
		$target = new \StdClass;
		$a = null;
		$c = null;

		try
		{
			EventReflection::from(WithReferencesEvent::class)->with([

				'target' => $target,
				'a' => &$a,
				'c' => &$c

			]);

			$this->fail("Expected exception.");
		}
		catch (\Exception $e)
		{
			$this->assertInstanceOf(\BadMethodCallException::class, $e);
			$this->assertContains('skipped', $e->getMessage());
		}
	}

	public function test_from()
	{
		$target = new \StdClass;
		$a = null;
		$b = null;
		$a_value = uniqid();
		$b_value = uniqid();

		$event = EventReflection::from(WithReferencesEvent::class)->with([

			'target' => $target,
			'a' => &$a,
			'b' => &$b,
			// 'c' is left to its default value

		]);

		/* @var $event WithReferencesEvent */

		$this->assertInstanceOf(WithReferencesEvent::class, $event);

		$event->a = $a_value;
		$event->b = $b_value;

		$this->assertSame($a_value, $event->a);
		$this->assertSame($b_value, $event->b);
		$this->assertSame($a_value, $a);
		$this->assertSame($b_value, $b);
		$this->assertSame('default', $event->c);
	}
}
