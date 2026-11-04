# API Architecture

## Principles and versioning
Expose a JSON REST API under /api/v1. Version the contract independently of internal class/table names. Use resource nouns and explicit command endpoints for state transitions. Breaking changes require a new version or documented migration window. Publish OpenAPI and contract tests with each module delivery.

## Authentication and authorization
Use Laravel Sanctum for interactive users. Service credentials for adapters must be narrowly scoped, rotated and stored outside source control. Authenticate every protected route; apply Policies/Gates for action-level authorization and record-level organizational scope. Return 401 for unauthenticated, 403 for forbidden, and avoid leaking whether protected records exist.

## Requests and responses
Use Form Requests for structural validation and domain services for invariants. Standard success resources include data, stable ID, status, timestamps and links/relationship references where appropriate. Lists are paginated, filtered and sorted only by allowlisted fields. Return decimal monetary values as decimal strings plus currency KES; never serialize money through float. Use UTC timestamps and a request/correlation ID.

Validation errors use a stable field-error structure; conflicts use 409 for stale state, duplicate/idempotency conflict or illegal transition; 422 for invalid input/domain preconditions. Do not return SQL, stack traces, secret values or sensitive bank/payroll fields.

## Command endpoints and idempotency
Use explicit commands such as POST /api/v1/budget/commitments/{id}/approve, /ledger/journals/{id}/post, /treasury/payment-batches/{id}/execute and /revenue/receipts/{id}/confirm. Commands declare permission, preconditions, transaction boundary, audit fact and emitted event. Require Idempotency-Key for payment execution, receipt confirmation, imports and other externally retried commands. Persist key, request fingerprint and final outcome; same key/same request returns original outcome, conflicting body returns 409.

## Financial transactions
Wrap state and balance changes in DB transactions; lock budget balance/commitment rows for overspend-sensitive checks. Never expose generic PATCH for posted journal/payment/receipt records. Corrections use controlled reverse/adjust commands with original reference. Enforce balanced journal before posting and open period from module 05. Separate AP voucher approval from Treasury payment execution.

## Security and operations
Apply rate limits to authentication, search-heavy and public callback endpoints. Validate webhook signatures/replay timestamps at gateway; impose body limits; redact logs. Do not trust frontend checks. Maintain audit actor, scope, correlation and causation metadata. Health endpoints expose only minimum data.

## Cross-module API and documentation
Use in-process application contracts for synchronous modular-monolith commands and versioned domain events/outbox for asynchronous notifications, reporting projections and external adapters. Avoid direct cross-module ORM writes. Every endpoint catalogue in module specs must identify method, route, responsible permission, validated fields/preconditions, response and errors.
