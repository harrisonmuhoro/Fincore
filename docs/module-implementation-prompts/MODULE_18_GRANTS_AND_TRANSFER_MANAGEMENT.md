# Module 18: Grants and Transfer Management — Implementation Prompt

Act as the implementation team for FinCore County, a modular-monolith county public financial management system using Laravel/PHP 8.3+, REST APIs, Sanctum, Laravel Policies/Gates, queues and scheduler; React/TypeScript/Vite/Tailwind/shadcn; MySQL 8 and Redis. Implement only this module's agreed scope in the existing repository.

## Module objective and boundary

Own grant registration, conditional/unconditional grants, intergovernmental transfers, allocations, disbursements, utilization, compliance and reporting.

Dependencies and consumers: Depends on financial master data, organization, budget, workflow, documents and treasury/ledger contracts. Grant records must not duplicate bank execution or posted journals.

Treat these boundaries as ownership guidance. Confirm against the architecture documents and current code before changing anything. Do not create duplicate master records or make this module authoritative for another module's transaction.

## Required execution sequence

### 1. Repository inspection
Inspect the complete relevant repository: instructions (including AGENTS.md), source, migrations, routes, tests, package/dependency manifests, configuration, and architecture documentation. Report existing patterns, dirty/unrelated work, current module status, and gaps. Preserve user changes.

### 2. Existing architecture compatibility
Map this module to current namespaces, conventions, API versioning, auth, UI shell, design system, database practices, events, queues and error handling. Read the system/module contracts and dependency matrix. If a hard dependency, data owner, or material business decision is undefined, document the blocker and implement only safely separable work. Do not introduce a parallel architecture or new dependency without justification.

### 3. Module objectives
Translate the objective above into in-scope use cases, actors, records, lifecycle states, reports, explicit out-of-scope items, and dependency contracts. Resolve no legal threshold or policy by guesswork; flag it for configuration or decision.

### 4. Database migrations
Define tables and ownership only after inspecting existing schema. Use versioned Laravel migrations, primary/foreign keys, indexes, unique constraints, status/timestamps and appropriate soft-delete/retention behavior. Use DECIMAL for money, never floating point. Prevent invalid cross-module ownership; reference other modules by stable IDs/contracts. Use transactions and locking for concurrency-sensitive financial operations. Include rollback safety and migration tests.

### 5. Backend implementation
Implement thin controllers and appropriately scoped Form Requests, models, domain services/actions, policies, events/listeners, jobs, resources and scheduled tasks as warranted by existing conventions. Enforce validation and invariants in the backend/domain layer. Use database transactions and idempotency where duplicate or concurrent operations could cause harm. Never silently edit/delete posted financial records; use authorized reversals/adjustments where applicable.

### 6. API implementation
Implement versioned REST endpoints consistent with the repository. Define methods, payload validation, response resources, pagination/filtering, error semantics, authentication and authorization. Protect sensitive fields, avoid leaking internal details, and make state-changing financial operations idempotent where appropriate. Update API documentation.

### 7. Frontend implementation
Build the module's required routes, pages, forms, tables, filters, dashboards and workflow controls using established React/TypeScript and UI patterns. Use strict types, accessible validation/error/loading/empty states, and server-enforced authorization. Connect every visible action to working behavior; do not add decorative or placeholder buttons.

### 8. Business workflow integration
Implement the module lifecycle, state transitions, validation, approval/return/rejection paths and final outcomes using the shared workflow engine when required. Publish/consume documented domain events and integrate notifications/documents through contracts. Keep ownership of business state here; avoid circular calls and duplicate transaction ownership.

### 9. Permissions and security
Add least-privilege permissions and organizational scope checks using existing IAM, Laravel Policies/Gates and middleware. Enforce segregation of duties and approval limits server-side. Protect bank, payroll, personal, audit and other sensitive data; validate uploads and secure downloads. Record privileged actions through the canonical audit contract.

### 10. Automated tests
Add focused tests at the repository's established levels: domain/unit, API/feature, integration and authorization/security. Cover validation, scopes, lifecycle transitions, dependency contracts, failures, retries/idempotency and relevant financial integrity/concurrency cases. Use fictional test data and isolated test fixtures.

### 11. Documentation
Update module/API/architecture documentation with ownership, workflows, schema, permissions, events, dependencies, configuration, operational behavior and unresolved decisions. Document setup or migrations only as appropriate to repository conventions.

### 12. Acceptance criteria
The module is complete only when its scoped use cases work end-to-end; data ownership and authorization are enforced; integrations follow documented contracts; UI actions work; migrations are safe; tests pass; documentation matches behavior; and no unresolved blocker is represented as completed functionality. Summarize evidence and any deferred decisions.

### 13. Regression checks
Run relevant existing checks for affected backend/frontend areas, migrations and API contracts. Inspect the final diff for accidental changes, duplicated ownership, secrets, unsafe financial types, unprotected endpoints and broken routes. Report commands and outcomes accurately; do not claim checks passed if they were not run.

## System-wide constraints
- Use fictional seed/test data. Do not imply official government ownership, approval, affiliation or integration.
- Do not claim IFMIS or external integration; use mock adapters until specifications, credentials and authorization exist.
- Keep approval thresholds and financial policies configurable; do not hardcode unvalidated legal thresholds.
- Secure every endpoint on the server; frontend visibility is not authorization.
- Avoid circular dependencies and speculative placeholder implementations.
- Before editing, state a concise inspection summary and proposed scoped changes. Then implement this module only and report changed files, verification, risks and open decisions.
*** End Patch
