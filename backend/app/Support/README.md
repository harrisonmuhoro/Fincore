# Shared support

This namespace is reserved for small cross-cutting primitives with more than one legitimate caller, such as correlation metadata or response helpers. Domain-specific rules stay with their owning module. Add abstractions only when an actual implementation needs them.
