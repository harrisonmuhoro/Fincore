# MODULE 25 — Integration and External System Gateway

## 1. Purpose and scope
Adapters, credentials refs, webhook ingress, retries, monitoring and transport audit. This module owns integration_connections, messages, webhooks, attempts, incidents.. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Adapters, credentials refs, webhook ingress, retries, monitoring and transport audit. Operational functions are limited to this domain; reference records remain administered by their owning module.

## 3. Actors and responsibilities
Integration operators configure connector metadata; security admins control credentials; domain owners approve payload contracts; support operators retry/reconcile failed transport, not business outcome.

## 4. Permissions and access restrictions
Permissions: module.view, create, update_draft, submit, review, approve, return, reject, administer, export. Apply least privilege and organizational/project/fund scopes on every record. Maker-checker separation for consequential operations; sensitive fields require explicit grants.

## 5. Module-specific business rules
Mock until authorized specs; signature/replay validation; secrets never returned. Keep any unverified statutory rule or threshold configurable and unresolved. Monetary values use DECIMAL and decimal-safe arithmetic.

## 6. Detailed business workflows
Register adapter contract/credentials reference → test connection → accept signed/replay-safe request → normalize/enqueue → retry with backoff → reconcile outcome/dead-letter; domain owner decides transaction result.
Initiation, mandatory validation and approval gates are defined above. On failure, Signature/replay/schema failure is rejected and logged; timeouts use bounded retry; uncertain transaction results require source-domain reconciliation, not duplicate submission.

## 7. State transitions and lifecycle rules
Use domain-specific transitions, not a generic status mutation endpoint. Preserve snapshots and decision history. Posted accounting records are immutable and corrected only by linked authorized reversal/adjustment. Closed periods reject financial posting. Other lifecycle statuses are defined by the workflow above and owner policy.

## 8. Database ownership and entity relationships
Candidate entities: integration_connections, messages, webhooks, attempts, incidents.. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
Laravel controllers adapt HTTP; Form Requests validate inputs; Eloquent models represent owned entities; services/actions enforce invariants and transaction boundaries; Policies/Gates authorize; Resources define response contracts; Events publish committed facts through outbox; listeners/jobs handle async/retry/scheduled work. Use DB transactions and row locks for concurrency-sensitive operations.

## 10. API contracts and endpoint responsibilities
/api/v1/integrations; /webhooks/{adapter}.
- GET /api/v1/integrations: scoped paginated list; module.view; validate allowlisted filters and pagination.
- POST /api/v1/integrations: create domain record; module.create; validate required fields and owner references.
- GET /api/v1/integrations/{id}: authorized detail and lifecycle metadata.
- PATCH /api/v1/integrations/{id}: update mutable draft fields; module.update_draft; reject immutable states.
- POST /api/v1/integrations/{id}/actions/{action}: explicit domain command; distinct permission, current-state/scope/reason validation and idempotency for sensitive operations.
Use /api/v1, Sanctum or approved service identity, stable resource IDs, UTC timestamps and decimal-safe serialization. Return consistent 401/403/404/409/422 errors; never expose stack traces or secrets.

## 11. Frontend responsibilities
React/TypeScript dashboard/list, detail/history/evidence, draft form, work queue and module-specific exception/reconciliation pages. Tables use scoped filters/pagination; forms show server errors; irreversible actions confirm. Tailwind/shadcn and typed API client; accessible loading/empty/error states, working controls, no frontend-only security.

## 12. Cross-module dependencies and integration events
Transports domain contracts, never owns domain transactions. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Unit-test rules, calculations and transitions; feature-test endpoints, authorization and scope; integration-test dependency contracts, events/outbox/retries; security-test IDOR, privilege escalation and data leakage. Financial modules additionally test DECIMAL, rollback, row locks/concurrency, overspend, balanced journals, duplicate payments, idempotency, reconciliation and period locks.

## 15. Acceptance criteria
All scoped use cases work end-to-end; invalid/out-of-scope actions fail; data ownership is respected; API/UI reflect lifecycle; events are retry-safe; audit is complete; applicable financial invariants hold; tests pass; documentation matches behavior; unresolved policy is not silently assumed.

## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Signature/replay/schema failure is rejected and logged; timeouts use bounded retry; uncertain transaction results require source-domain reconciliation, not duplicate submission.

## Reporting and operational visibility
Adapter health, queued/sent/failed/dead-letter messages, retry counts, reconciliation status and credential expiry metadata (never secret values).

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
