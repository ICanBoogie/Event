# Event

[![Release](https://img.shields.io/packagist/v/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)
[![Build Status](https://img.shields.io/github/workflow/status/ICanBoogie/Event/test)](https://github.com/ICanBoogie/Event/actions?query=workflow%3Atest)
[![Code Quality](https://img.shields.io/scrutinizer/g/ICanBoogie/Event/master.svg)](https://scrutinizer-ci.com/g/ICanBoogie/Event)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Event/master.svg)](https://coveralls.io/r/ICanBoogie/Event)
[![Packagist](https://img.shields.io/packagist/dt/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)

The **icanboogie/event** allows you to provide hooks which other developers can attach to, to be
notified when certain events occur inside the application and take action.

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





## Getting started

To be emitted, events need an event collection, which holds event hooks. Because a new event
collection is created for you when required, you don't need to setup one yourself. Still you might
want to do so if you have a bunch of event hooks that you need to attach while creating the event
collection. To do so, you need to define a _provider_ that will return your event collection when
required.

The following example demonstrates how to setup a provider that instantiates an event collection
with event hooks provided by an application configuration:

```php
<?php

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;

/* @var $app */

EventCollectionProvider::define(function() use ($app) {

	static $collection;

	return $collection ?: $collection = new EventCollection($app->configs['event']);

});

# Getting the event collection

$events = EventCollectionProvider::provide();
# or
$events = \ICanBoogie\get_events();
```





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
	 * @param Response $response
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





## Attaching event hooks

Event hooks are attached using the `attach()` method of an event collection. The `attach()` method
is smart enough to create the event type from the parameters type. This works with any callable: closure, invokable objects, static class methods, functions.

The following example demonstrates how a closure may be attached to a `ICanBoogie\Operation::process:before` event type.

```php
<?php

use ICanBoogie\Operation;

/* @var $events \ICanBoogie\EventCollection */

$events->attach(function(Operation\BeforeProcessEvent $event, Operation $target) {

	// …

});
```

The following example demonstrates how an invokable object may be attached to that same event type.

```php
<?php

class ValidateOperation
{
	private $rules;

	public function __construct(array $rules)
	{
		$this->rules = $rules;
	}

	public function __invoke(Operation\BeforeProcessEvent $event, Operation $target)
	{
		// …
	}
}

// …

/* @var $events \ICanBoogie\EventCollection */
/* @var $rules array */

$events->attach(new ValidateOperation($rules));
```





### Attaching an event hook to a specific target

Using the `attach_to()` method, an event hook can be attached to a specific target, and is only
invoked for that target.

```php
<?php

use ICanBoogie\Routing\Controller;

// …

/* @var $events \ICanBoogie\EventCollection */

$events->attach_to($controller, function(Controller\ActionEvent $event, Controller $target) {

	echo "invoked!";

});

$controller_clone = clone $controller;

new Controller\ActionEvent($controller_clone, …);   // nothing happens
new Controller\ActionEvent($controller, …);         // echo "invoked!"
```





### Attaching a _one time_ event hook

The `once()` method attaches event hooks that are automatically detached after they have been used.

```php
<?php

use ICanBoogie\Event;

$n = 0;

/* @var $events \ICanBoogie\EventCollection */

$events->once('flash', function() use(&$n) {

	$n++;

});

new Event(null, 'flash');
new Event(null, 'flash');
new Event(null, 'flash');

echo $n;   // 1
```





### Attaching event hooks using the `events` config

When the package is bound to [ICanBoogie][] by [icanboogie/bind-event][], event hooks may be
attached from the `events` config. Have a look at the [icanboogie/bind-event][] package for
further details.





### Attaching event hooks to the _finish chain_

The _finish chain_ is executed after the event chain was traversed without being stopped.

The following example demonstrates how an event hook may be attached to the _finish chain_ of
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

/* @var $events \ICanBoogie\EventCollection */

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
	'response' => $response,
	'rc' => &$rc

]);

$event->rc = "ABBA";
echo $rc;  // ABBA
```

> Array keys must match construct arguments, an exception will fire otherwise. Also, if a
> constructor argument must be passed by reference keep in mind that it must be passed by
> reference in the array as well.

The event can later be fired using the `fire()` method:

```php
<?php

/* @var $event \ICanBoogie\Event */

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





## Helpers

- [`get_events`][]: Returns the current event collection. If the event collection provider is defined the method defines one that provides a new [EventCollection][] instance.





----------





## Installation

```bash
composer require icanboogie/event
```





## Documentation

The package is documented as part of the [ICanBoogie][] framework
[documentation][]. You can generate the documentation for the
package and its dependencies with the `make doc` command. The documentation is generated in
the `build/docs` directory. [ApiGen](http://apigen.org/) is required. The directory can later
be cleaned with the `make clean` command.





## Testing

Run `make test-container` to create and log into the test container, then run `make test` to run the
test suite. Alternatively, run `make test-coverage` to run the test suite with test coverage. Open
`build/coverage/index.html` to see the breakdown of the code coverage.





## License

**icanboogie/event** is released under the [New BSD License](LICENSE).





[documentation]:         https://icanboogie.org/api/event/3.0/
[Event]:                 https://icanboogie.org/api/event/3.0/class-ICanBoogie.Event.html
[EventHook]:             https://icanboogie.org/api/event/3.0/class-ICanBoogie.EventHook.html
[EventProfiler]:         https://icanboogie.org/api/event/3.0/class-ICanBoogie.EventProfiler.html
[EventReflection]:       https://icanboogie.org/api/event/3.0/class-ICanBoogie.EventReflection.html
[EventCollection]:       https://icanboogie.org/api/event/3.0/class-ICanBoogie.EventCollection.html
[`get_events`]:          https://icanboogie.org/api/event/3.0/function-ICanBoogie.get_events.html
[ICanBoogie]:            https://icanboogie.org/
[icanboogie/bind-event]: https://github.com/ICanBoogie/bind-event
