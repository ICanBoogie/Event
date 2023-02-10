# Event

[![Release](https://img.shields.io/packagist/v/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)
[![Code Quality](https://img.shields.io/scrutinizer/g/ICanBoogie/Event/master.svg)](https://scrutinizer-ci.com/g/ICanBoogie/Event)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Event/master.svg)](https://coveralls.io/r/ICanBoogie/Event)
[![Downloads](https://img.shields.io/packagist/dt/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)

The **icanboogie/event** allows you to provide hooks which other developers can attach to, to be
notified when certain events occur inside the application and take action.

Inside [ICanBoogie][], events are often used to alter initial parameters, take action before/after
an operation is processed or when it fails, take action before/after a request is dispatched or to
rescue an exception.



#### Installation

```bash
composer require icanboogie/event
```



### Feature highlights

* Easily implementable.
* Events are typed.
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


When a `ProcessEvent` is emitted with a `…\News\Operation\SaveOperation` instance, all event hooks
attached to the classes for this event are called, starting from the event hooks attached to the
instance class (`…\News\Operation\SaveOperation`) all the way up to those attached to its root
class.

Thus, event hooks attached to the `…\Node\Operation\SaveOperation` class are called when a
`ProcessEvent` event is fired with `…\News\Operation\SaveOperation` instance. One could consider
that event hooks are _inherited_.





## Getting started

To be emitted, events need an event collection, which holds event hooks. Because a new event
collection is created for you when required, you don't need to set up one yourself. Still you might
want to do so if you have a bunch of event hooks that you need to attach while creating the event
collection. To do so, you need to define a _provider_ that will return your event collection when
required.

The following example demonstrates how to setup a provider that instantiates an event collection
with event hooks provided by an application configuration:

```php
<?php

namespace ICanBoogie;

/* @var Application $app */

EventCollectionProvider::define(function() use ($app) {

	static $collection;

	return $collection ??= new EventCollection($app->configs['event']);

});

# Getting the event collection

$events = EventCollectionProvider::provide();
# or
$events = get_events();
```





## Typed events

All events are instance of the [Event][] class, and because it is abstract it needs to be extended.

The following code demonstrates how a `ProcessEvent` class may be defined:

```php
<?php

namespace ICanBoogie\Operation;

use ICanBoogie\Event;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Operation;

class ProcessEvent extends Event
{
	/**
	 * Reference to the response result property.
	 */
	public mixed $result;

	public function __construct(
	    Operation $target,
	    public readonly Request $request,
	    public readonly Response $response,
	    mixed &$result
    ) {
		$this->result = &$result;

		parent::__construct($target);
	}
}
```





### Event types

If an event has a target, the event is obtained using the `for()` method and the target class or
object. If an event doesn't have a target, the event type is the event class.





### Namespacing and naming

Event classes should be defined in a namespace unique to their target object. Events targeting
`ICanBoogie\Operation` instances should be defined in the `ICanBoogie\Operation` namespace.





## Firing events

Events are fired with the `emit()` function.

```php
<?php

namespace ICanBoogie;

/* @var Event $event */

emit($event);
```





## Attaching event hooks

Event hooks are attached using the `attach()` method of an event collection. The `attach()` method
is smart enough to create the event type from the parameters type. This works with any callable:
closure, invokable objects, static class methods, functions.

The following example demonstrates how a closure may be attached to a `BeforeProcessEvent` event.

```php
<?php

namespace ICanBoogie

$detach = $events->attach(function(Operation\BeforeProcessEvent $event, Operation $target) {

	// …

});

# or, if the event doesn't have a target

$detach = $events->attach(function(Operation\BeforeProcessEvent $event) {

	// …

});

$detach(); // You can detach if you no longer want to listen.
```

The following example demonstrates how an invokable object may be attached to that same event type.

```php
<?php

namespace ICanBoogie

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

/* @var $events EventCollection */
/* @var $rules array<string, mixed> */

$events->attach(new ValidateOperation($rules));
```





### Attaching an event hook to a specific target

Using the `attach_to()` method, an event hook can be attached to a specific target, and is only
invoked for that target.

```php
<?php

namespace ICanBoogie;

use ICanBoogie\Routing\Controller;

// …

/* @var $events EventCollection */

$detach = $events->attach_to($controller, function(Controller\ActionEvent $event, Controller $target) {

	echo "invoked!";

});

$controller_clone = clone $controller;

emit(new Controller\ActionEvent($controller_clone, …));   // nothing happens, it's a clone
emit(new Controller\ActionEvent($controller, …));         // echo "invoked!"

// …

$detach(); // You can detach if you no longer want to listen.
```





### Attaching a _one time_ event hook

The `once()` method attaches event hooks that are automatically detached after they have been used.

```php
<?php

namespace ICanBoogie;

/* @var $events EventCollection */

$n = 0;

$events->once(MyEvent $event, function() use(&$n) {

	$n++;

});

emit(new MyEvent());
emit(new MyEvent());
emit(new MyEvent());

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

namespace ICanBoogie;

class CountEvent extends Event
{
	public function __construct(
	    public string $count = "0"
    ) {
		parent::__construct();
	}
}

/* @var $events EventCollection */

$events->attach(function(CountEvent $event): void {

	$event->count .= "2";

});

$events->attach(function(CountEvent $event): void {

	$event->count .= "1";

});

$events->attach('count', function(CountEvent $event): void {

	$event->chain(function(CountEvent $event) {

		$event->count .= "3";

	});
});

$event = emit(new CountEvent(0));

echo $event->count; // 0123
```





## Breaking an event hook chain

The processing of an event hook chain can be broken by an event hook using the `stop()` method:

```php
<?php

use ICanBoogie\Operation;

function on_event(Operation\ProcessEvent $event, Operation $operation): void
{
	$event->rc = true;
	$event->stop();
}
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

- `get_events()`: Returns the current event collection. A new one is created if none exist.
- `emit()`: Emit the specified event.





----------



## Continuous Integration

The project is continuously tested by [GitHub actions](https://github.com/ICanBoogie/Event/actions).

[![Tests](https://github.com/ICanBoogie/Event/workflows/test/badge.svg?branch=master)](https://github.com/ICanBoogie/Event/actions?query=workflow%3Atest)
[![Static Analysis](https://github.com/ICanBoogie/Event/workflows/static-analysis/badge.svg?branch=master)](https://github.com/ICanBoogie/Event/actions?query=workflow%3Astatic-analysis)
[![Code Style](https://github.com/ICanBoogie/Event/workflows/code-style/badge.svg?branch=master)](https://github.com/ICanBoogie/Event/actions?query=workflow%3Acode-style)



## Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in
this project and its community, you are expected to uphold this code.



## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.



## License

**icanboogie/event** is released under the [BSD-3-Clause](LICENSE).



[Event]:                 lib/Event.php
[EventProfiler]:         lib/EventProfiler.php
[EventCollection]:       lib/EventCollection.php
[ICanBoogie]:            https://icanboogie.org/
[icanboogie/bind-event]: https://github.com/ICanBoogie/bind-event
