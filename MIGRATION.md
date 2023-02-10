# Migration

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

None
