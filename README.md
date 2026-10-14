# Event

[![Release](https://img.shields.io/packagist/v/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)
[![Code Coverage](https://coveralls.io/repos/github/ICanBoogie/Event/badge.svg?branch=7.0)](https://coveralls.io/r/ICanBoogie/Event?branch=7.0)
[![Downloads](https://img.shields.io/packagist/dt/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)

The **icanboogie/event** allows you to provide hooks which other developers can attach to, to be
notified when certain events occur inside the application and take action.

Inside [ICanBoogie][], events are often used to alter initial parameters, take action before/after
an operation is processed or when it fails, take action before/after a request is dispatched or to
rescue an exception.



#### Installation

```shell
composer require icanboogie/event
```



### Feature highlights

* Easily implementable.
* Events are typed.
* Events usually have a sender object, but simpler event types can also be emitted.
* Event hooks are attached to classes rather than objects, and they are inherited.
* Event hooks can be attached using the `#[Listener]` attribute or a configuration builder.
* Event hooks can be attached to a _finish chain_ that is executed after the event hooks chain.
* Execution of the event chain can be stopped.





## A twist on the Observer pattern

The pattern used by the API is similar to the [Observer pattern](http://en.wikipedia.org/wiki/Observer_pattern),
although instead of attaching event hooks to objects they are attached to their class. When an
event is fired by a sender object, the hierarchy of its class is used to filter event
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

> [!NOTE]
> Hooks attached to the sender's class come first, then those attached to its parent classes
> (`get_parent_class()`), and finally those attached to the interfaces it implements
> (`class_implements()`), so a hook can be attached to an interface such as `Throwable`.
> See [Events with a sender](#events-with-a-sender).





## Getting started

To be emitted, events need an event collection, which holds event hooks. Because a new event
collection is created for you when required, you don't need to set one up yourself. Still, you might
want to do so if you have a bunch of event hooks that you need to attach while creating the event
collection. To do so, you need to define a _provider_ that returns your event collection when
required.

The following example demonstrates how to set up a provider that instantiates an event collection
with event hooks provided by an app configuration:

```php
<?php

namespace ICanBoogie;

use ICanBoogie\Event\Config;

/* @var Application $app */

EventCollectionProvider::define(function() use ($app) {

    static $collection;

    return $collection ??= new EventCollection($app->configs[Config::class]);

});

# Getting the event collection

$events = EventCollectionProvider::provide();
# or
$events = get_events();
```





## Typed events

Events are subclasses of the [Event][] class.

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

If an event has a sender, its qualified type is obtained using the `for()` method and the sender
class or object, e.g. `RuntimeException::ICanBoogie\HTTP\RecoverEvent`. If an event doesn't have a
sender, the event type is the event class, e.g. `ICanBoogie\HTTP\RecoverEvent`.

The sender is the object passed to `parent::__construct()`. It is available as `$event->sender`,
only when the event was constructed with one.





### Namespacing and naming

Event classes should be defined in a namespace unique to their sender object. Events sent by
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
is smart enough to create the event type from the parameter types.

* A hook with a single parameter (`fn(MyEvent $event)`) is attached to the **bare** event type,
  `MyEvent`. It is called for every `MyEvent`, whatever the sender, after the hooks attached to the sender.
* A hook with two parameters (`fn(MyEvent $event, Sender $sender)`) is attached to the qualified type
  `Sender::MyEvent`. It is called for events emitted with a sender of that class, or one of its subclasses.

The following example demonstrates how a closure may be attached to a `BeforeProcessEvent` event.

```php
<?php

namespace ICanBoogie

$detach = $events->attach(function(Operation\BeforeProcessEvent $event, Operation $target) {

    // …

});

# or, if the event doesn't have a sender

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





### Attaching an event hook to a specific sender

Using the `attach_to()` method, an event hook can be attached to a specific sender instance, and is
only invoked for that sender.

```php
<?php

namespace ICanBoogie;

use ICanBoogie\Routing\Controller;

// …

/* @var $events EventCollection */

$detach = $events->attach_to($controller, function(Controller\ActionEvent $event, Controller $sender) {

    echo "invoked!";

});

$controller_clone = clone $controller;

emit(new Controller\ActionEvent($controller_clone, …));   // nothing happens, it's a clone
emit(new Controller\ActionEvent($controller, …));         // echo "invoked!"

// …

$detach(); // You can detach if you no longer want to listen.
```





### Attaching a _one-time_ event hook

The `once()` method attaches an event hook that is automatically detached after it's been used.

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





### Attaching event hooks using a config

Event hooks can be declared in an `ICanBoogie\Event\Config`, created with `ConfigBuilder`, and given
to the `EventCollection` constructor or to `attach_many()`.

```php
<?php

use ICanBoogie\Event\ConfigBuilder;
use ICanBoogie\HTTP\RecoverEvent;

$config = (new ConfigBuilder())
    ->attach(MyEvent::class, $listener)                                     // event without sender
    ->attach_to(RuntimeException::class, RecoverEvent::class, $listener)    // event with sender, by sender class
    ->use_attributes()                                                      // listeners found with #[Listener]
    ->build();
```

When the package is bound to [ICanBoogie][] by [icanboogie/bind-event][], the config is built from
the `event` config fragment (e.g. `app/all/config/event.php`), using a `ConfigBuilder` of the same API.
See the README of [icanboogie/bind-event][] for the fragment, services, and troubleshooting.

> [!NOTE]
> With `ConfigBuilder`, listeners are callables. To reference a service, use
> `ICanBoogie\Service\ref()`. The service must be public, otherwise the failure only occurs when
> the first event is emitted.



#### Using the `#[Listener]` attribute

`use_attributes()` collects methods marked with `ICanBoogie\Event\Listener` (using
[olvlvl/composer-attribute-collector][]).

```php
<?php

use ICanBoogie\Event\Listener;

final class OnProcess
{
    #[Listener]
    public function __invoke(ProcessEvent $event): void
    {
        // …
    }

    #[Listener]
    public static function on_save(SaveEvent $event, Node $sender): void
    {
        // …
    }
}
```

- Static methods are attached as `[class, method]`. Non-static methods must be `__invoke()`, and are
  resolved as services (the class by default, or `#[Listener(ref: 'service_id')]`).
- One parameter attaches to the bare event type, two parameters attach to the event type of the
  sender class or interface (given by the second parameter, which must be loadable).
- There is one attribute per method, and a method names a single sender type. To listen to several
  unrelated senders, use `attach_to()` once per type on the config builder.





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

$events->attach(function(CountEvent $event): void {

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





## Events with a sender

Events with a sender are the most common source of confusion.

### The qualified type includes the sender class

The sender is whatever object the event passes to `parent::__construct()`:

```php
<?php

class MyEvent extends Event
{
   public function __construct(RuntimeException $exception)
   {
       parent::__construct($exception); // the exception is the sender
   }
}

emit(new MyEvent(new RuntimeException()));
```

This event is emitted with the type `RuntimeException::MyEvent` (sender class, then event class), not
`MyEvent`. A one-parameter hook, `fn(MyEvent $event)`, or `ConfigBuilder::attach(MyEvent::class, …)`,
is attached to the bare `MyEvent` and catches every `MyEvent`, whatever its sender. To only catch the events of
a given sender, use a two-parameter hook, `fn(MyEvent $event, RuntimeException $sender)`, `attach_to()`, or
`ConfigBuilder::attach_to()`.

### Matching the sender's class or interfaces

Hooks are matched against the sender's class, its parent classes, then its interfaces.
`ConfigBuilder::attach_to()` requires a loadable class or interface. Exceptions and errors have two
class roots (`Exception` and `Error`), but both implement `Throwable`, which catches them all:

```php
<?php

$builder->attach_to(Throwable::class, RecoverEvent::class, $listener);
```

### Checking the wiring

A unit test of the listener proves nothing about registration. Emit the event with a real sender,
then compare `$event->qualified_type` with `$events->get_hooks($event->qualified_type)`, or look at
`EventProfiler::iter_unused()`, which lists the types that were emitted with no hook.

Events without a sender are simpler to use: consider whether your event really needs one.



## Profiling events

The [EventProfiler][] class is used to collect timing information about unused events and event
hook calls. All time information is measured in floating microtime.

```php
<?php

use ICanBoogie\EventProfiler;

foreach (EventProfiler::iter_unused() as $record)    // UnusedRecord
{
    echo "$record->type at $record->time";
}

foreach (EventProfiler::iter_calls() as $record)     // CallRecord
{
    echo "$record->type took $record->duration, handled by " . get_debug_type($record->hook);
}

EventProfiler::reset_unused();
EventProfiler::reset_calls();
```

An _unused_ event is an emitted event with no attached hooks. If a hook you expected isn't called,
check that its event type shows up there.





## Helpers

- `get_events()`: Returns the current event collection. A new one is created if none exist.
- `emit()`: Emit the specified event.





----------



## Continuous Integration

The project is continuously tested by [GitHub Actions](https://github.com/ICanBoogie/Event/actions).

[![Tests](https://github.com/ICanBoogie/Event/actions/workflows/test.yml/badge.svg?branch=7.0)](https://github.com/ICanBoogie/Event/actions/workflows/test.yml)
[![Static Analysis](https://github.com/ICanBoogie/Event/actions/workflows/static-analysis.yml/badge.svg?branch=7.0)](https://github.com/ICanBoogie/Event/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/ICanBoogie/Event/actions/workflows/code-style.yml/badge.svg?branch=7.0)](https://github.com/ICanBoogie/Event/actions/workflows/code-style.yml)



## Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in
this project and its community, you're expected to uphold this code.



## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details.



[Event]:                 lib/Event.php
[EventProfiler]:         lib/EventProfiler.php
[EventCollection]:       lib/EventCollection.php
[ICanBoogie]:            https://icanboogie.org/
[olvlvl/composer-attribute-collector]: https://github.com/olvlvl/composer-attribute-collector
[icanboogie/bind-event]: https://github.com/ICanBoogie/bind-event
