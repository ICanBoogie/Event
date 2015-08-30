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

$autoload = require __DIR__ . '/../vendor/autoload.php';
$autoload->addPsr4('ICanBoogie\\EventTest\\', __DIR__ . '/EventTest/');

namespace ICanBoogie\EventTest;

function before_target_practice(Target\BeforePracticeEvent $event, Target $target)
{

}
