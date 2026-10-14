# CHANGELOG

## v7.0.0

### New Requirements

PHP 8.4+

### New features

- Listeners can be attached to the interfaces implemented by the sender of an event, e.g. `Throwable`. They are called
  after the listeners attached to the sender's class and parent classes.

### Deprecated Features

None

### Backward Incompatible Changes

- Listeners attached to a bare event type, e.g. `attach(fn(MyEvent $event) …)`, are now also called for events emitted with a sender, after the listeners attached to the sender. They used to never match, silently.
- "Hook" is now consistently named "listener": `EventHookReflection` is now `ListenerReflection`, `EventCollection::get_hooks()` is now `get_listeners()`, and `CallRecord::$hook` is now `$listener`.
- `Event` dropped `AccessorTrait` in favor of property hooks.
- `EventProfiler` uses `UnusedRecord` and `CallRecord` instead of simple arrays.
- The packages `icanboogie/accessor` and `icanboogie/common` are no longer used.

### Other changes

- Documentation: the README describes `ConfigBuilder`, `#[Listener]`, the sender/class-hierarchy matching rules, and the current `EventProfiler` API.
- Require `composer/pcre` ^3.2 and `symfony/finder` ^7.0 for development, to avoid the PHP 8.4 deprecations of the older versions that `composer/class-map-generator` allows.



## v4.x to v6.0

### New Requirements

- PHP 8.1+

### New features

None

### Backward Incompatible Changes

- The `Event` class is now abstract and requires extension.

- "target" references have been renamed as "sender". For instance, the `Event::$target` property has
  been renamed to `sender`.

- Events are not longer emitted during instantiation, you need to use the `emit()` function for
  that. All code related to event reflexion to create non-firing events has been removed.

    ```php
    <?php

    namespace ICanBoogie;

    $event = new SampleEvent();
    ```

    ```php
    <?php

    namespace ICanBoogie;

    $event = emit(new SampleEvent());
    ```

- `EventCollection::attach()`, `EventCollection::attach_to()`, `EventCollection::once()` now require
  a `Closure` and no longer a callable. `EventCollection::attach_many()` still works with regular
  callables.

- The `type` parameter of the `Event` constructor has been removed. The type is now the class of the
  event.

    ```php
    <?php

    namespace ICanBoogie;

    $event = new SampleEvent(type: 'sample-event');
    ```

    ```php
    <?php

    namespace ICanBoogie;

    $event = new SampleEvent();
    ```

- The `payload` parameter of the `Event` constructor has been removed.

    ```php
    class ProcessEvent extends Event
    {
        public $values;

        public function __construct(A $target, array $payload = [])
        {
            parent::__construct($target, 'process', $payload);
        }
    }
    ```

    ```php
    class ProcessEvent extends Event
    {
        public function __construct(A $sender, public array $values)
        {
            parent::__construct($sender);
        }
    }
    ```

- Callables without senders are now supported for attachment:

    ```php
    $events->attach('count', function(CountEvent $event): void {
        // …
    }
    ```

    ```php
    $events->attach(function(CountEvent $event): void {
        // …
    }
    ```

- Dropped `Event::$used` and `Event::$used_by` properties. The information is still available in the
  profiler.

### Deprecated Features

None

### Other Changes

- Fixed an `Error` when an event listener is defined as `[ $object, 'method' ]`, the object was converted to a string to build the listener's key.
