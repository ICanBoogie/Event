# Migration

## v4.x to v6.0

### Breaking changes

- `EventCollection::attach()`, `EventCollection::attach_to()`, `EventCollection::once()` now require
  a `Closure` and no longer a callable. `EventCollection::attach_many()` still works with regular
  callables.
