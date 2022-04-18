# Migration

## v4.x to v6.0

### Breaking changes

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
