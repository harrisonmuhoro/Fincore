# Engineering Standards

- Modular monolith; one owner per business transaction and table set.
- Laravel controllers remain thin; validate requests and enforce Policies/Gates server-side.
- React uses strict TypeScript and accessible, functional controls.
- Version database migrations; test rollback and integrity constraints.
- Use DECIMAL, database transactions, row locks, idempotency and immutable posted journals with linked reversals.
- Apply least privilege, organization scope, maker-checker, MFA for privileged users and private validated files.
- Publish versioned event contracts through an outbox; make consumers idempotent.
- Use fictional seed/test data; secrets and real financial/personnel data do not belong in source or fixtures.
- External integrations remain mocks pending approved specs and authorization.
- Keep API documentation, tests and operational behavior aligned with implementation.

## Phase 0 implementation defaults

- Backend runtime: PHP 8.3+, Laravel 13, Composer 2. Frontend runtime: Node.js 24, React, strict TypeScript and Vite.
- REST endpoints are prefixed `/api/v1`; JSON responses use `success`, `message`, `data`, and `meta.request_id`. Errors use HTTP status codes and a structured `errors` member for validation failures.
- MySQL uses InnoDB and `utf8mb4`. Use Laravel reversible migrations, snake_case plural tables, stable primary keys, explicit indexed foreign keys, and UTC timestamps. Add soft deletion only where lifecycle and retention require it.
- Use configured private local storage by default; never expose uploaded files through a public web root. External object storage requires an approved deployment decision.
- Use the API CORS allow-list and application rate limiter. Do not trust forwarded proxy headers unless trusted proxy addresses are explicitly configured by the deployment.
- Laravel Pint, PHPUnit, TypeScript checking, Oxlint, Vitest, production builds and dependency vulnerability audits are CI gates.
- Redis readiness is enforced with `REDIS_REQUIRED=true` in environments where Redis is a required queue/cache/lock dependency. No production environment should rely on the local-development optional default.
