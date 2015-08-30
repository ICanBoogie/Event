# Event

[![Release](https://img.shields.io/packagist/v/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)
[![Build Status](https://img.shields.io/travis/ICanBoogie/Event/master.svg)](http://travis-ci.org/ICanBoogie/Event)
[![HHVM](https://img.shields.io/hhvm/icanboogie/event.svg)](http://hhvm.h4cc.de/package/icanboogie/event)
[![Code Quality](https://img.shields.io/scrutinizer/g/ICanBoogie/Event/master.svg)](https://scrutinizer-ci.com/g/ICanBoogie/Event)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Event/master.svg)](https://coveralls.io/r/ICanBoogie/Event)
[![Packagist](https://img.shields.io/packagist/dt/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)

This package allows developers to provide hooks which other developers
may hook into, to be notified when certain events occur inside the application and take action.

Inside [ICanBoogie][], events are often used to alter initial parameters,
take action before/after an operation is processed or when it fails, take action before/after a
request is dispatched or to rescue an exception.





### Feature highlights

* Easily implementable.
* Events are typed.
* Events are fired as they are instantiated.
* Events usually have a target object, but simpler event types can also be emitted.
* Event hooks are attached to classes rather than objects, and they are inherited.
* Event hooks can be attached to a _finish chain_ that is executed after the event hooks chain. 
* Execution of the event chain can be stopped.





## A twist on the Observer pattern

The pattern used by the API is similar to the [Observer pattern](http://en.wikipedia.org/wiki/Observer_pattern),
although instead of attaching event hooks to objects they are attached to their class. When an
event is fired upon a target object, the hierarchy of its class is used to filter event
hooks.

Consider the following class hierarchy:

    ICanBoogie\Operation
    └─ ICanBoogie\Module\Operation\SaveOperation
        └─ Icybee\Modules\Node\Operation\SaveOperation
            └─ Icybee\Modules\Content\Operation\SaveOperation
                └─ Icybee\Modules\News\Operation\SaveOperation


When the `process` event is fired upon a `…\News\Operation\SaveOperation` instance, all event
hooks attached to the classes for this event are called, starting from the event hooks attached
to the instance class (`…\News\Operation\SaveOperation`) all the way up to those attached
to its root class.

Thus, event hooks attached to the `…\Node\Operation\SaveOperation` class are called
when the `process` event is fired upon a `…\News\Operation\SaveOperation` instance. One could
consider that event hooks are _inherited_.





## Typed events

An instance of an [Event][] subclass is used
to provide contextual information about an event to the event hooks processing it. It is passed as
the first argument, with the target object as second argument (if any). This instance contain
information directly relating to the type of event they accompany.

For example, a `process` event is usually instantiated from a `ProcessEvent` class, and a
`process:before` event—fired before a `process` event—is usually instantiated
from a `BeforeProcessEvent` instance.

The following code demonstrates how a `ProcessEvent` class may be defined for a `process` event type:

```php
<?php

namespace ICanBoogie\Operation;

use ICanBoogie\Event;
use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Operation;

/**
 * Event class for the `ICanBoogie\Operation::process` event.
 *
 * @property mixed $rc
 * @property-read Response $response
 * @property-read Request $request
 */
class ProcessEvent extends Event
{
	/**
	 * Reference to the response result property.
	 *
	 * @var mixed
	 */
	private $rc;
	
	protected function get_rc()
	{
		return $this->rc;
	}
	
	protected function set_rc($rc)
	{
		$this->rc = $rc;
	}

	/**
	 * The response object of the operation.
	 *
	 * @var Response
	 */
	private $response;
	
	protected function get_response()
	{
		return $this->response;
	}

	/**
	 * The request that triggered the operation.
	 *
	 * @var Request
	 */
	private $request;
	
	protected function get_request()
	{
		return $this->request;
	}

	/**
	 * The event is constructed with the type `process`.
	 *
	 * @param Operation $target
	 * @param Request $request
	 * @param response $response
	 * @param mixed $rc
	 */
	public function __construct(Operation $target, Request $request, Response $response, &$rc)
	{
		$this->request = $request;
		$this->response = $response;
		$this->rc = &$rc;
	
		parent::__construct($target, 'process');
	}
}
```





### Event types

The event type is usually the name of an associated method. For example, the `process` event
type is fired after the `ICanBoogie\Operation::process` method was called, and the `process:before`
event type is fired before.





### Namespacing and naming

Event classes should be defined in a namespace unique to their target object. Events
targeting `ICanBoogie\Operation` instances should be defined in the `ICanBoogie\Operation`
namespace.

The class name should match the event type. `ProcessEvent` for the `process` event type,
`BeforeProcessEvent` for the `process:before` event.





## Firing events

Events are fired as they are instantiated.

The following example demonstrates how the `process` event is fired upon an
`ICanBoogie\Operation` instance:

```php
<?php

namespace ICanBoogie;

class Operation
{
	// …

	public function __invoke()
	{
		// …

		$response->rc = $this->process();

		new Operation\ProcessEvent($this, $request, $response, $response->rc); 

		// …
	}

	// …
}
```

Note that before events can be emitted the event collection to use must be defined. This is done
by patching the `get()` method of the [EventCollection][] class:

```php
<?php

use ICanBoogie\EventCollection:

$events = new EventCollection([

	'ICanBoogie\Operation::process' => [
	
		'my_callback'
	
	]
]);

EventCollection::set_instance_provider(function () use ($events) {

    return $events;

});
```

Using an instance provider, you could create the collection just in
time.





## Attaching event hooks

Event hooks are attached using the `attach()` method of an event collection. The `attach()` method
is smart enough to create the event type from the parameters type. This works with any callable: closure, invokable objects, static class methods, functions.

The following example demonstrates how a closure may be attached to a `ICanBoogie\Operation::process:before` event type.

```php
<?php

use ICanBoogie\Operation;

$events->attach(function(Operation\BeforeProcessEvent $event, Operation $target) {

	// …

});
```

The following example demonstrates how an invokable object may be attached to that same event type.

```php

class ValidateOperation
{
	private $rules;

	public function __construct($rules)
	{
		$this->rules = $rules;
	}

	public function __invoke(Operation\BeforeProcessEvent $event, Operation $target)
	{
		// …
	}
}

// …

$events->attach(new ValidateOperation($rules);
```





### Attaching an event hook to a specific target

Using the `attach_to()` method, an event hook can be attached to a specific target, and is only
invoked for that target.

```php
<?php

use ICanBoogie\Routing\Controller;

…

$events->attach_to($controller, function(Controller\ActionEvent $event, Controller $target) {

	echo "invoked!";

});

$controller_clone = clone $controller;

new Controller\ActionEvent($controller_clone, …);   // nothing happens
new Controller\ActionEvent($controller, …);         // echo "invoked!"
```





### Attaching an event hook that is to be used once

The `once()` method attaches event hooks that are automatically detached after they have been used.

```php
<?php

$n = 0;

$events->once('flash', function() use(&n) {

	$n++;

});

new Event(null, 'flash');
new Event(null, 'flash');
new Event(null, 'flash');

echo $n;   // 1
```





### Attaching event hooks using the `events` config

When the package is bound to [ICanBoogie][] by [icanboogie/bind-event][], event hook may be
attached from the `events` config. Have a look at the [icanboogie/bind-event][] package for
further details.





### Attaching event hooks to the _finish chain_

The _finish chain_ is executed after the event chain was traversed without being stopped.

The following example demonstrates how an event hook can be attached to the _finish chain_ of
the `count` event to obtain the string "0123". If the third event hook was defined like the
others we would obtain "0312".

```php
<?php

class CountEvent extends \ICanBoogie\Event
{
	public $count;

	public function __construct($count)
	{
		$this->count = $count;

		parent::__construct(null, 'count');
	}
}

$events->attach('count', function(CountEvent $event) {

	$event->count .= 2;

});

$events->attach('count', function(CountEvent $event) {

	$event->count .= 1;

});

$events->attach('count', function(CountEvent $event) {

	$event->chain(function(CountEvent $event) {

		$event->count .= 3;

	});
});

$event = new CountEvent(0);

echo $event->count; // 0123
```





## Breaking an event hook chain

The processing of an event hook chain can be broken by an event hook using the `stop()` method:

```php
<?php

use ICanBoogie\Operation;

function on_event(Operation\ProcessEvent $event, Operation $operation)
{
	$event->rc = true;
	$event->stop();
}
```





## Instantiating _non-firing_ events

Events are designed to be fired as they are instantiated, but sometimes you want to be able to
create an [Event][] instance without it to be fired immediately, for instance when you
need to test that event, or alter it before it is fired.

The `from()` method creates _non-firing_ event instances from an array of parameters.

The following example demonstrates how to create an _non-firing_ instance of the
`ProcessEvent` class we saw earlier:

```php
<?php

use ICanBoogie\Operation\ProcessEvent;
use ICanBoogie\EventReflection;

$rc = null;

// …

$event = ProcessEvent::from([

	'target' => $operation,
	'request' => $request,
	'response' => $response
	'rc' => &$rc

]);

$event->rc = "ABBA";
echo $rc;  // ABBA
```

The event can later be fired using the `fire()` method:

```php
<?php

$event->fire();
```





## Profiling events

The [EventProfiler][] class is used to collect timing information about unused events and event
hook calls. All time information is measured in floating microtime.

```php
<?php

use ICanBoogie\EventProfiler;

foreach (EventProfiler::$unused as list($time, $type))
{
	// …
}

foreach (EventProfiler::$calls as list($time, $type, $hook, $started_at))
{
	// …
}
```





----------





## Requirements

The package requires PHP 5.5 or later.





## Installation

The recommended way to install this package is through [Composer](http://getcomposer.org/):

```
$ composer require icanboogie/event
```





### Cloning the repository

The package is [available on GitHub](https://github.com/ICanBoogie/Event), its repository can be
cloned with the following command line:

	$ git clone https://github.com/ICanBoogie/Event.git





## Documentation

The package is documented as part of the [ICanBoogie][] framework
[documentation][]. You can generate the documentation for the
package and its dependencies with the `make doc` command. The documentation is generated in
the `build/docs` directory. [ApiGen](http://apigen.org/) is required. The directory can later
be cleaned with the `make clean` command.





## Testing

The test suite is ran with the `make test` command. [PHPUnit](https://phpunit.de/) and [Composer](http://getcomposer.org/) need to be globally available to run the suite. The command installs dependencies as required. The `make test-coverage` command runs test suite and also creates an HTML coverage report in `build/coverage`. The directory can later be cleaned with the `make clean` command.

The package is continuously tested by [Travis CI](http://about.travis-ci.org/).

[![Build Status](https://img.shields.io/travis/ICanBoogie/Event/master.svg)](https://travis-ci.org/ICanBoogie/Event)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Event/master.svg)](https://coveralls.io/r/ICanBoogie/Event)





## License

**icanboogie/event** is licensed under the New BSD License - See the [LICENSE](LICENSE) file for details.





[documentation]:         http://api.icanboogie.org/event/1.4/
[Event]:                 http://api.icanboogie.org/event/1.4/class-ICanBoogie.Event.html
[EventHook]:             http://api.icanboogie.org/event/1.4/class-ICanBoogie.EventHook.html
[EventProfiler]:         http://api.icanboogie.org/event/1.4/class-ICanBoogie.EventProfiler.html
[EventReflection]:       http://api.icanboogie.org/event/1.4/class-ICanBoogie.EventReflection.html
[EventCollection]:       http://api.icanboogie.org/event/1.4/class-ICanBoogie.EventCollection.html
[icanboogie/bind-event]: https://github.com/ICanBoogie/bind-event
[ICanBoogie]:            https://github.com/ICanBoogie/ICanBoogie
