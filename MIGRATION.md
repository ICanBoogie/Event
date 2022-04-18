# Migration

## v4.x to v6.0

### Breaking changes

- Events are not longer emitted during instantiation, you need to use the `emit()` function for
  that. All code related to event reflexion to create non-firing events has been removed.

    ```php
    <?php

    namespace ICanBoogie;

    $event = new Event('created');
    ```

    ```php
    <?php

    namespace ICanBoogie;

    $event = emit(new Event('created'));
    ```

- `EventCollection::attach()`, `EventCollection::attach_to()`, `EventCollection::once()` now require
  a `Closure` and no longer a callable. `EventCollection::attach_many()` still works with regular
  callables.

- The `payload` attribute of the `Event` constructor has been removed. Better write event classes
  for now one.

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
        public function __construct(A $target, public array $values)
        {
            parent::__construct($target, 'process');
        }
    }
    ```

- Dropped `Event::$used` and `Event::$used_by` properties. The information is still available in the
  profiler.
