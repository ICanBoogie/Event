# Event

[![Release](https://img.shields.io/packagist/v/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)
[![Code Coverage](https://coveralls.io/repos/github/ICanBoogie/Event/badge.svg?branch=7.0)](https://coveralls.io/r/ICanBoogie/Event?branch=7.0)
[![Downloads](https://img.shields.io/packagist/dt/icanboogie/event.svg)](https://packagist.org/packages/icanboogie/event)

The **icanboogie/event** package lets your code emit events, and lets other developers attach
listeners to be notified when they occur and take action.

Inside [ICanBoogie][], events are often used to alter initial parameters, take action before/after
an operation is processed or when it fails, take action before/after a request is dispatched or to
rescue an exception.



#### Installation

```shell
composer require icanboogie/event
```



### Feature highlights

- Events are typed.
- Events usually have a sender object, but simpler event types can also be emitted.
- Event listeners are attached to classes rather than objects, and they are inherited.
- Event listeners can be attached at runtime, with a configuration builder, or discovered with the `#[Listener]` attribute.
- Event listeners can be attached to a _finish chain_ that is executed after the event listeners chain.
- Execution of the event chain can be stopped.





## A twist on the Observer pattern

The pattern used by the API is similar to the [Observer pattern](http://en.wikipedia.org/wiki/Observer_pattern),
although instead of attaching event listeners to objects they are attached to their class. When an
event is fired by a sender object, the hierarchy of its class is used to filter event
listeners.

Consider the following class hierarchy:

    App\Document
    └─ App\Invoice
        └─ App\RecurringInvoice


When a `SavedEvent` is emitted with a `RecurringInvoice` instance, all event listeners attached to
the classes for this event are called, starting from the event listeners attached to the instance
class (`RecurringInvoice`) all the way up to those attached to its root class (`Document`).

Thus, event listeners attached to the `Invoice` class are called when a `SavedEvent` is emitted with
a `RecurringInvoice` instance. One could consider that event listeners are _inherited_.

> [!NOTE]
> Listeners attached to the sender's class come first, then those attached to its parent classes
> (`get_parent_class()`), and finally those attached to the interfaces it implements
> (`class_implements()`), so a listener can be attached to an interface such as `Throwable`.
> See [Events with a sender](#events-with-a-sender).





## Getting started

Events are emitted with `emit()`, which uses the current _event collection_, the object that holds
the listeners. A collection is created for you when required, so you can attach listeners and emit
events right away. Define a _provider_ when you want the collection to start with listeners, for
instance the ones of a [config](#attaching-event-listeners-using-a-config):

```php
<?php

namespace ICanBoogie;

use ICanBoogie\Event\Config;

/* @var Config $config */

EventCollectionProvider::define(function() use ($config) {

    static $collection;

    return $collection ??= new EventCollection($config);

});

# Getting the event collection

$events = EventCollectionProvider::provide();
# or
$events = get_events();
```





## Typed events

Events are subclasses of the [Event][] class. The simplest event has no sender and no data, its
class is its type:

```php
<?php

namespace App;

use ICanBoogie\Event;

final class CacheClearedEvent extends Event
{
}
```

An event is about something, an object that is its _sender_, and carries data. The sender is passed
to `parent::__construct()`. The following code demonstrates how a `SavedEvent` class may be defined,
for the `Document` objects of an application:

```php
<?php

namespace App\Document;

use App\Document;
use ICanBoogie\Event;

final class SavedEvent extends Event
{
    public function __construct(
        Document $document,
        public readonly bool $created,
    ) {
        parent::__construct($document);
    }
}
```





### Event types

If an event has a sender, its qualified type is obtained using the `for()` method and the sender
class or object, e.g. `App\Document::App\Document\SavedEvent`. If an event doesn't have a
sender, the event type is the event class, e.g. `App\CacheClearedEvent`.

The sender is the object passed to `parent::__construct()`. It is available as `$event->sender`,
only when the event was constructed with one.





### Namespacing and naming

Event classes should be defined in a namespace unique to their sender object. Events sent by
`App\Document` instances should be defined in the `App\Document` namespace, e.g.
`App\Document\SavedEvent`.





## Firing events

Events are fired with the `emit()` function.

```php
<?php

namespace ICanBoogie;

/* @var Event $event */

emit($event); // returns $event, for chaining
```





## Attaching event listeners

Event listeners are attached using the `attach()` method of an event collection. The `attach()`
method is smart enough to create the event type from the parameter types.

- A listener with a single parameter (`fn(MyEvent $event)`) is attached to the **bare** event type,
  `MyEvent`. It is called for every `MyEvent`, whatever the sender, after the listeners attached
  to the sender.
- A listener with two parameters (`fn(MyEvent $event, Sender $sender)`) is attached to the qualified
  type `Sender::MyEvent`. It is called for events emitted with a sender of that class, or one of
  its subclasses.

Listeners attached with `attach()` are called in reverse order: the last one attached is called
first. Listeners of a config keep the order they were given.

The following example demonstrates how a closure may be attached to a `SavedEvent` event.

```php
<?php

namespace App;

use App\Document\SavedEvent;

/* @var ICanBoogie\EventCollection $events */

$detach = $events->attach(function(SavedEvent $event, Document $document) {

    // …

});

# or, to listen to the event whatever its sender

$detach = $events->attach(function(SavedEvent $event) {

    // …

});

$detach(); // You can detach if you no longer want to listen.
```

The following example demonstrates how an invokable object may be attached to that same event type.

```php
<?php

namespace App;

use App\Document\SavedEvent;

final class AuditTrail
{
    public function __construct(
        private readonly AuditLog $log,
    ) {
    }

    public function __invoke(SavedEvent $event, Document $document): void
    {
        $this->log->record($document, $event->created ? 'created' : 'updated');
    }
}

// …

/* @var ICanBoogie\EventCollection $events */
/* @var AuditLog $log */

$events->attach(new AuditTrail($log));
```





### Attaching an event listener to a specific sender

Using the `attach_to()` method, an event listener can be attached to a specific sender instance, and
is only invoked for that sender.

```php
<?php

namespace App;

use App\Document\SavedEvent;

use function ICanBoogie\emit;

/* @var ICanBoogie\EventCollection $events */
/* @var Document $document */

$detach = $events->attach_to($document, function(SavedEvent $event, Document $sender) {

    echo "invoked!";

});

$document_clone = clone $document;

emit(new SavedEvent($document_clone, created: false));   // nothing happens, it's a clone
emit(new SavedEvent($document, created: false));         // echo "invoked!"

$detach(); // You can detach if you no longer want to listen.
```





### Attaching a _one-time_ event listener

The `once()` method attaches an event listener that is automatically detached after it's been used.

```php
<?php

namespace App;

use function ICanBoogie\emit;

/* @var ICanBoogie\EventCollection $events */

$n = 0;

$events->once(function(CacheClearedEvent $event) use (&$n): void {

    $n++;

});

emit(new CacheClearedEvent());
emit(new CacheClearedEvent());
emit(new CacheClearedEvent());

echo $n;   // 1
```





### Attaching event listeners using a config

Event listeners can be declared in an `ICanBoogie\Event\Config`, created with `ConfigBuilder`, and
given to the `EventCollection` constructor or to `attach_many()`. A `Config` can be exported with
`var_export()`, to be cached as PHP code.

```php
<?php

use ICanBoogie\Event\ConfigBuilder;
use ICanBoogie\HTTP\RecoverEvent;

$config = new ConfigBuilder()
    ->attach(MyEvent::class, $listener)                                     // event without sender
    ->attach_to(RuntimeException::class, RecoverEvent::class, $listener)    // event with sender, by sender class
    ->enable_discovery()                                                    // #[Listener] methods, found by build()
    ->build();

$events = new EventCollection($config);
```

When the package is bound to [ICanBoogie][] by [icanboogie/bind-event][], the config is built from
the `event` config fragment (e.g. `app/all/config/event.php`), using the same `ConfigBuilder`.
See the README of [icanboogie/bind-event][] for the fragment, services, and troubleshooting.

> [!NOTE]
> With `ConfigBuilder`, listeners are callables. To reference a service, use
> `ICanBoogie\Service\ref()`. The service must be public, otherwise the failure only occurs when
> the first event is emitted.



#### Using the `#[Listener]` attribute

`enable_discovery()` makes `build()` look for the methods marked with `ICanBoogie\Event\Listener`,
and attach them after the listeners attached explicitly. Nothing is collected until `build()` is
invoked. `disable_discovery()` turns it off again, discovery is off by default.

Discovery relies on [olvlvl/composer-attribute-collector][], which your application has to require.
Load the `vendor/attributes.php` file it generates before building the config, see its
documentation.

```php
<?php

namespace App;

use App\Document\SavedEvent;
use ICanBoogie\Event\Listener;

final class OnSaved
{
    #[Listener]
    public function __invoke(SavedEvent $event): void
    {
        // …
    }

    #[Listener]
    public static function on_invoice_saved(SavedEvent $event, Invoice $sender): void
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
- The config can't be built, and a `LogicException` is thrown, if the collector is not available, a
  non-static method is not `__invoke()`, a method has more than two parameters, or the parameter types
  are not suitable (the first must be an `Event`).





### Attaching event listeners to the _finish chain_

The _finish chain_ is executed after the event chain was traversed without being stopped.

The following example demonstrates how an event listener may be attached to the _finish chain_ of
the `CountEvent` event to obtain the string "0123". Listeners attached with `attach()` are called in
reverse order, so the third one is called first and registers `"3"` in the _finish chain_. If it was
defined like the others we would obtain "0312".

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

$event = emit(new CountEvent());

echo $event->count; // 0123
```





## Breaking an event listener chain

The processing of an event listener chain can be broken by an event listener using the `stop()`
method. The listeners that follow in the chain are not called, nor are the listeners of the _finish
chain_.

```php
<?php

namespace App;

use App\Document\SavedEvent;

function skip_drafts(SavedEvent $event, Document $document): void
{
    if ($document->is_draft) {
        $event->stop();
    }
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

This event is emitted with the type `RuntimeException::MyEvent` (sender class, then event class),
not `MyEvent`. A one-parameter listener, `fn(MyEvent $event)`, or
`ConfigBuilder::attach(MyEvent::class, …)`, is attached to the bare `MyEvent` and catches every
`MyEvent`, whatever its sender. To only catch the events of a given sender, use a two-parameter
listener, `fn(MyEvent $event, RuntimeException $sender)`, `attach_to()`, or
`ConfigBuilder::attach_to()`.

### Matching the sender's class or interfaces

Listeners are matched against the sender's class, its parent classes, then its interfaces.
`ConfigBuilder::attach_to()` requires a loadable class or interface. Exceptions and errors have two
class roots (`Exception` and `Error`), but both implement `Throwable`, which catches them all:

```php
<?php

$builder->attach_to(Throwable::class, RecoverEvent::class, $listener);
```

### Checking the wiring

A unit test of the listener proves nothing about registration. Emit the event with a real sender,
then compare `$event->qualified_type` with `$events->get_listeners($event->qualified_type)`, or look
at `EventProfiler::iter_unused()`, which lists the types that were emitted with no listener.

Events without a sender are simpler to use: consider whether your event really needs one.



## Profiling events

The [EventProfiler][] class is used to collect timing information about unused events and event
listener calls. All time information is measured in floating microtime.

```php
<?php

use ICanBoogie\EventProfiler;

foreach (EventProfiler::iter_unused() as $record)    // UnusedRecord
{
    echo "$record->type at $record->time";
}

foreach (EventProfiler::iter_calls() as $record)     // CallRecord
{
    echo "$record->type took $record->duration, handled by " . get_debug_type($record->listener);
}

EventProfiler::reset_unused();
EventProfiler::reset_calls();
```

An _unused_ event is an emitted event with no attached listeners. If a listener you expected isn't
called, check that its event type shows up there.





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
