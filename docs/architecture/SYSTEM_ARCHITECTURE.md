# System Architecture

Deploy as a modular monolith: Laravel API/application, React client, MySQL 8 transactional store, Redis queues/cache/locks where appropriate, workers/scheduler and private document storage. Phase 0 establishes the Laravel/React foundation; domain namespaces and module packages are added with each approved module implementation.

Layers: HTTP adapters (auth/validation/resources), application actions (orchestration and transaction boundaries), domain modules (rules/state/tables), infrastructure (persistence/queues/storage/adapters), frontend (typed API and screens). Each module owns writes to its records. Cross-module interaction uses contracts and stable IDs, not informal model mutation. Use transactional outbox, idempotent consumers, correlation IDs, and asynchronous delivery; avoid cycles.

Enforce server-side Policies/Gates, org scoping, MFA for privileged users, secrets management, private files, structured monitoring, backups and restore drills. Decide tenancy, data residency, availability and recovery objectives before production.
