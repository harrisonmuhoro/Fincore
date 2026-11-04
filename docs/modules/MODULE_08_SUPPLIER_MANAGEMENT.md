# MODULE 08 — Supplier Management

## 1. Purpose and scope
Supplier profile, verification, documents, status and restricted bank details. This module owns suppliers, classifications, verifications, bank_accounts, performance.. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Supplier profile, verification, documents, status and restricted bank details. Provide record creation/maintenance, lifecycle actions, scoped search, exception handling, history/evidence and domain-specific operational reporting. Admin tools manage this domain's configuration only.

## 3. Actors and responsibilities
Supplier Management. Operators prepare and maintain records; authorized reviewers decide; administrators maintain module configuration; auditors have scoped read-only access. Consumers access only documented services/views.

## 4. Permissions and access restrictions
Permissions: module.view, create, update_draft, submit, review, approve, return, reject, administer, export. Apply least privilege and organizational/project/fund scopes on every record. Maker-checker separation for consequential operations; sensitive fields require explicit grants.

## 5. Module-specific business rules
Independent bank change verification; duplicate review; inactive supplier blocked for new awards. Validate references against the owning module contract, uniqueness, scope and lifecycle state. Keep unverified law and approval thresholds configurable and unresolved. Monetary values use DECIMAL and decimal-safe arithmetic.

## 6. Detailed business workflows
Register supplier → validate identity and duplicates → verify documents/tax/bank separately → approve/activate → update by independently verified change; suspend blocks new awards.
Start only with valid references and an authorized actor. Validate before state changes; record actor, timestamp, reason and correlation ID. Return requires reason and preserves prior decisions. Rejection is terminal unless approved reopen policy exists. Finalization is atomic; reconcile uncertain external results before retry.

## 7. State transitions and lifecycle rules
Use domain-specific transitions, not a generic status mutation endpoint. Preserve snapshots and decision history. Posted accounting records are immutable and corrected only by linked authorized reversal/adjustment. Closed periods reject financial posting. Other lifecycle statuses are defined by the workflow above and owner policy.

## 8. Database ownership and entity relationships
Candidate entities: suppliers, classifications, verifications, bank_accounts, performance.. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
Laravel controllers adapt HTTP; Form Requests validate inputs; Eloquent models represent owned entities; services/actions enforce invariants and transaction boundaries; Policies/Gates authorize; Resources define response contracts; Events publish committed facts through outbox; listeners/jobs handle async/retry/scheduled work. Use DB transactions and row locks for concurrency-sensitive operations.

## 10. API contracts and endpoint responsibilities
/api/v1/suppliers; restricted /{id}/bank-details.
- GET /api/v1/suppliers: scoped paginated list; module.view; validate allowlisted filters and pagination.
- POST /api/v1/suppliers: create domain record; module.create; validate required fields and owner references.
- GET /api/v1/suppliers/{id}: authorized detail and lifecycle metadata.
- PATCH /api/v1/suppliers/{id}: update mutable draft fields; module.update_draft; reject immutable states.
- POST /api/v1/suppliers/{id}/actions/{action}: explicit domain command; distinct permission, current-state/scope/reason validation and idempotency for sensitive operations.
Use /api/v1, Sanctum or approved service identity, stable resource IDs, UTC timestamps and decimal-safe serialization. Return consistent 401/403/404/409/422 errors; never expose stack traces or secrets.

## 11. Frontend responsibilities
React/TypeScript dashboard/list, detail/history/evidence, draft form, work queue and module-specific exception/reconciliation pages. Tables use scoped filters/pagination; forms show server errors; irreversible actions confirm. Tailwind/shadcn and typed API client; accessible loading/empty/error states, working controls, no frontend-only security.

## 12. Cross-module dependencies and integration events
Consumed by procurement/contracts/AP. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Unit-test rules, calculations and transitions; feature-test endpoints, authorization and scope; integration-test dependency contracts, events/outbox/retries; security-test IDOR, privilege escalation and data leakage. Financial modules additionally test DECIMAL, rollback, row locks/concurrency, overspend, balanced journals, duplicate payments, idempotency, reconciliation and period locks.

## 15. Acceptance criteria
All scoped use cases work end-to-end; invalid/out-of-scope actions fail; data ownership is respected; API/UI reflect lifecycle; events are retry-safe; audit is complete; applicable financial invariants hold; tests pass; documentation matches behavior; unresolved policy is not silently assumed.

## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
