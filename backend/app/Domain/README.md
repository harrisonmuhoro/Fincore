# Domain module boundary

Business capabilities belong under `App\Domain\{ModuleName}` when the module is approved for implementation. A module owns its domain rules, state transitions and records; cross-module writes must use explicit application contracts. Phase 0 adds no business-domain code or schema.
