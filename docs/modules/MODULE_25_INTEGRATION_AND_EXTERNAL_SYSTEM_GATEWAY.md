# MODULE 25 — Integration and External System Gateway

## 1. Purpose and scope
Adapters, credentials refs, webhook ingress, retries, monitoring and transport audit. This module owns integration_connections, messages, webhooks, attempts, incidents. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Adapters, credentials refs, webhook ingress, retries, monitoring and transport audit. Operational functions are limited to this domain; reference records remain administered by their owning module.

## 3. Actors and responsibilities
Integration operators configure connector metadata; security admins control credentials; domain owners approve payload contracts; support operators retry/reconcile failed transport, not business outcome.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `integration.message.view` | Domain reader | No | Enforce record and organizational scope. |
| `integration.message.create` | Domain maker | No | Validate required owner references; maker cannot self-approve. |
| `integration.message.update_draft` | Assigned record owner | No | Draft-only edits; preserve version and change history. |
| `integration.message.submit` | Domain maker | No | Validate mandatory fields and evidence before submission. |
| `integration.message.approve` | Independent delegated approver | Yes | Must differ from maker; authority and organization scope checked server-side. |
| `integration.message.reject` | Assigned reviewer | No | Require reason; preserve decision history and permit correction only via defined return/resubmit. |
| `integration.message.export` | Authorized analyst | Conditional for sensitive data | Apply same row scope and field masking as on-screen access. |
## 5. Module-specific business rules
Mock until authorized specs; signature/replay validation; secrets never returned. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Register adapter contract/credentials reference → test connection → accept signed/replay-safe request → normalize/enqueue → retry with backoff → reconcile outcome/dead-letter; domain owner decides transaction result.
Initiation, mandatory validation and approval gates are defined above. On failure, Signature/replay/schema failure is rejected and logged; timeouts use bounded retry; uncertain transaction results require source-domain reconciliation, not duplicate submission.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Actor / preconditions | Side effects / failure |
|---|---|---|---|---|
| Register adapter contract/credentials reference | test connection | test connection | Integration operators configure connector metadata; security admins control credentials; domain owners approve payload contracts; support operators retry/reconcile failed transport, not business outcome. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| test connection | accept signed/replay-safe request | accept signed/replay-safe request | Integration operators configure connector metadata; security admins control credentials; domain owners approve payload contracts; support operators retry/reconcile failed transport, not business outcome. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| accept signed/replay-safe request | normalize/enqueue | normalize/enqueue | Integration operators configure connector metadata; security admins control credentials; domain owners approve payload contracts; support operators retry/reconcile failed transport, not business outcome. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| normalize/enqueue | retry with backoff | retry with backoff | Integration operators configure connector metadata; security admins control credentials; domain owners approve payload contracts; support operators retry/reconcile failed transport, not business outcome. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| retry with backoff | reconcile outcome/dead-letter; domain owner decides transaction result. | reconcile outcome/dead-letter; domain owner decides transaction result. | Integration operators configure connector metadata; security admins control credentials; domain owners approve payload contracts; support operators retry/reconcile failed transport, not business outcome. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |

No caller may set state directly. Apply idempotency to commands whose retry could create duplicate effects. Financial reversals and period locks are enforced by financial owners (05/07/12/13/14/15 as applicable), not copied into unrelated domains.
## 8. Database ownership and entity relationships
integration_connections, messages, webhooks, attempts, incidents. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and authorization | Response / errors |
|---|---|---|---|
| POST | `/api/v1/integration/messages/{id}/retry` | Retry safe transport failure | `integration.message.retry`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/integration/messages/{id}/replay` | Approve duplicate-safe replay | `integration.message.replay`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/integration/connectors/{id}/activate` | Activate approved connector configuration | `integration.connector.activate`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |

Commands are transaction-safe; sensitive/retryable commands require an Idempotency-Key. Return stable error envelopes and redact sensitive fields.
## 11. Frontend responsibilities
Provide a Integration and External System Gateway register, record detail/history, draft form, review/approval queue, and exception view appropriate to the states above. Include scoped search and filters, evidence links, role-specific command availability, validation feedback and stale-version recovery. The server remains authoritative for every action; never show an unconfirmed command as successful.
## 12. Cross-module dependencies and integration events
Transports domain contracts, never owns domain transactions. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific duplicate message deduplication, dead-letter and replay scenarios.
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
