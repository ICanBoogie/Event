# Event

[![Release](https://img.shields.io/github/release/ICanBoogie/Event.svg)](https://github.com/ICanBoogie/Event/releases)
[![Build Status](https://img.shields.io/travis/ICanBoogie/Event/1.3.svg)](http://travis-ci.org/ICanBoogie/Event)
[![HHVM](https://img.shields.io/hhvm/icanboogie/event/1.3.svg)](http://hhvm.h4cc.de/package/icanboogie/event)
[![Code Quality](https://img.shields.io/scrutinizer/g/ICanBoogie/Event/1.3.svg)](https://scrutinizer-ci.com/g/ICanBoogie/Event)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Event/1.3.svg)](https://coveralls.io/r/ICanBoogie/Event)
[![Packagist](https://img.shields.io/packagist/dt/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)

The API provided by the Event package allows developers to provide hooks which other developers
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
    └─ ICanBoogie\SaveOperation
        └─ Icybee\Modules\Node\SaveOperation
            └─ Icybee\Modules\Content\SaveOperation
                └─ Icybee\Modules\News\SaveOperation


When the `process` event is fired upon a `Icybee\Modules\News\SaveOperation` instance, all event
hooks attached to the classes for this event are called, starting from the event hooks attached
to the instance class (`Icybee\Modules\News\SaveOperation`) all the way up to those attached
to its root class.

Thus, event hooks attached to the `Icybee\Modules\Node\SaveOperation` class are called
when the `process` event is fired upon a `Icybee\Modules\News\SaveOperation` instance. One could
consider that event hooks are _inherited_.





## Typed events

An instance of an [Event][] subclass is used
to provide contextual information about an event to the event hooks processing it. It is passed as
the first argument, with the target object as second argument (if any). This instance contain
information directly relating to the type of event they accompany.

For example, a `process` event is usually accompanied by a `ProcessEvent` instance, and a
`process:before` event—fired before a `process` event—is usually accompanied by
a `BeforeProcessEvent` instance. Here after is the definition of the `ProcessEvent` class for the
`process` event type, which is fired on `ICanBoogie\Operation` instances:

```php
<?php

namespace ICanBoogie\Operation;

/**
 * Event class for the `ICanBoogie\Operation::process` event.
 */
class ProcessEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the response result property.
	 *
	 * @var mixed
	 */
	public $rc;

	/**
	 * The response object of the operation.
	 *
	 * @var \ICanBoogie\HTTP\Response
	 */
	public $response;

	/**
	 * The request that triggered the operation.
	 *
	 * @var \ICanBoogie\HTTP\Request
	 */
	public $request;

	/**
	 * The event is constructed with the type `process`.
	 *
	 * @param \ICanBoogie\Operation $target
	 * @param array $payload
	 */
	public function __construct(\ICanBoogie\Operation $target, array $payload)
	{
		parent::__construct($target, 'process', $payload);
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

Events are fired simply by instantiating an event class.

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

		new Operation\ProcessEvent($this, array('rc' => &$response->rc, 'response' => $response, 'request' => $request)); 

		// …
	}

	// …
}
```

Note that before events can be emitted the event collection to use must be defined. This is done
by patching the `get()` method of the [Events][] class:

```php
<?php

use ICanBoogie\Events:

$events = new Events(array(

	'ICanBoogie\Operation::process' => array
	(
		'my_callback'
	)

));

Events::patch('get', function() use($events) { return $events; });
```

Using this technique you could also patch the `get()` method and create the collection just in
time. 





## Attaching event hooks

Event hooks are attached using the `attach()` method of an event collection. The `attach()` method
is smart enough to create the event type from the parameters type. In the following example, the
event hook is attached to the `ICanBoogie\Operation::process:before` event type.

```php
<?php

use ICanBoogie\Operation;

$events->attach(function(Operation\BeforeProcessEvent $event, Operation $operation) {

	// …

}); 
```





### Attaching an event hook that is be used once

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





### Attaching event hooks using the `hooks` config

With [ICanBoogie][], the `hooks` config can be used to define event hooks.

The following example demonstrate how a website can attach hooks to be notified when nodes are
saved (or nodes subclasses), and when an authentication exception is thrown during the dispatch
of a request.

```php
<?php

// config/hooks.php

return array
(
	'events' => array
	(
		'Icybee\Modules\Nodes\SaveOperation::process' => 'Website\Hooks::on_nodes_save',
		'ICanBoogie\AuthenticationRequired::rescue' => 'Website\Hooks::on_authentication_required_rescue'
	)
);
```





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

		parent::__construct(null, 'count', array());
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





## Breaking an event hooks chain

The processing of an event hooks chain can be broken by an event hook using the `stop()` method:

```php
<?php

use ICanBoogie\Operation;

function on_event(Operation\ProcessEvent $event, Operation $operation)
{
	$event->rc = true;
	$event->stop();
}
```





## ICanBoogie auto-config

The package supports the auto-config feature of the framework [ICanBoogie][] and provides a
config constructor as well as a lazy getter for the `events` property:

```<?php

$core = new ICanBoogie\Core($auto_config);

$core->configs['events']; // obtain the "events" config.
$core->events;            // obtain an Events instance created with the "events" config.
```

Note: This feature is only available for [ICanBoogie][] 2.x.





----------





## Requirement

The package requires PHP 5.4 or later.





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
[documentation](http://icanboogie.org/docs/). The documentation for the package and its
dependencies can be generated with the `make doc` command. The documentation is generated in
the `docs` directory using [ApiGen](http://apigen.org/). The package directory can later by
cleaned with the `make clean` command.

The following classes are documented: 

- [Event][]
- [EventHook][]
- [Events][]





## Testing

The test suite is ran with the `make test` command. [Composer](http://getcomposer.org/) is
automatically installed as well as all the dependencies required to run the suite. The package
directory can later be cleaned with the `make clean` command.

The package is continuously tested by [Travis CI](http://about.travis-ci.org/).

[![Build Status](https://img.shields.io/travis/ICanBoogie/Event/1.3.svg)](https://travis-ci.org/ICanBoogie/Event)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Event/1.3.svg)](https://coveralls.io/r/ICanBoogie/Event)





## License

ICanBoogie/Event is licensed under the New BSD License - See the [LICENSE](LICENSE) file for details.





[Event]: http://icanboogie.org/docs/class-ICanBoogie.Event.html
[EventHook]: http://icanboogie.org/docs/class-ICanBoogie.EventHook.html
[Events]: http://icanboogie.org/docs/class-ICanBoogie.Events.html
[ICanBoogie]: https://github.com/ICanBoogie/ICanBoogie
