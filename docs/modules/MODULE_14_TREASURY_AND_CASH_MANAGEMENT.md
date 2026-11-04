# MODULE 14 — Treasury and Cash Management

## 1. Purpose and scope
Bank registry, cashbook, payment batches, execution tracking, statements, reconciliation. This module owns bank_accounts, cashbook_entries, payment_batches, statements, matches. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Bank registry, cashbook, payment batches, execution tracking, statements, reconciliation. Operational functions are limited to this domain; reference records remain administered by their owning module.

## 3. Actors and responsibilities
Treasury preparers build batches; independent releasers authorize; execution officers submit; reconcilers match statements; bank adapter reports outcomes; AP cannot execute.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `treasury.payment_batch.view` | Domain reader | No | Enforce record and organizational scope. |
| `treasury.payment_batch.create` | Domain maker | No | Validate required owner references; maker cannot self-approve. |
| `treasury.payment_batch.update_draft` | Assigned record owner | No | Draft-only edits; preserve version and change history. |
| `treasury.payment_batch.submit` | Domain maker | No | Validate mandatory fields and evidence before submission. |
| `treasury.payment_batch.approve` | Independent delegated approver | Yes | Must differ from maker; authority and organization scope checked server-side. |
| `treasury.payment_batch.reject` | Assigned reviewer | No | Require reason; preserve decision history and permit correction only via defined return/resubmit. |
| `treasury.payment_batch.export` | Authorized analyst | Conditional for sensitive data | Apply same row scope and field masking as on-screen access. |
## 5. Module-specific business rules
Idempotent execution; segregate prepare/release/reconcile; uncertain outcome queried first. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Build payment batch from approved vouchers → validate beneficiary/bank/duplicates/funding → dual release → submit to bank adapter → record confirmed/failed/unknown → investigate unknown before retry → import statement and reconcile.
Initiation, mandatory validation and approval gates are defined above. On failure, Bank outcome unknown enters pending inquiry and cannot be resubmitted automatically; failed payments require classified reason; unmatched statement lines stay unreconciled.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Actor / preconditions | Side effects / failure |
|---|---|---|---|---|
| Build payment batch from approved vouchers | validate beneficiary/bank/duplicates/funding | validate beneficiary/bank/duplicates/funding | Treasury preparers build batches; independent releasers authorize; execution officers submit; reconcilers match statements; bank adapter reports outcomes; AP cannot execute. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| validate beneficiary/bank/duplicates/funding | dual release | dual release | Treasury preparers build batches; independent releasers authorize; execution officers submit; reconcilers match statements; bank adapter reports outcomes; AP cannot execute. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| dual release | submit to bank adapter | submit to bank adapter | Treasury preparers build batches; independent releasers authorize; execution officers submit; reconcilers match statements; bank adapter reports outcomes; AP cannot execute. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| submit to bank adapter | record confirmed/failed/unknown | record confirmed/failed/unknown | Treasury preparers build batches; independent releasers authorize; execution officers submit; reconcilers match statements; bank adapter reports outcomes; AP cannot execute. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| record confirmed/failed/unknown | investigate unknown before retry | investigate unknown before retry | Treasury preparers build batches; independent releasers authorize; execution officers submit; reconcilers match statements; bank adapter reports outcomes; AP cannot execute. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| investigate unknown before retry | import statement and reconcile. | import statement and reconcile. | Treasury preparers build batches; independent releasers authorize; execution officers submit; reconcilers match statements; bank adapter reports outcomes; AP cannot execute. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |

No caller may set state directly. Apply idempotency to commands whose retry could create duplicate effects. Financial reversals and period locks are enforced by financial owners (05/07/12/13/14/15 as applicable), not copied into unrelated domains.
## 8. Database ownership and entity relationships
bank_accounts, cashbook_entries, payment_batches, statements, matches. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and authorization | Response / errors |
|---|---|---|---|
| POST | `/api/v1/treasury/payment-batches/{id}/release` | Release approved batch for execution | `treasury.payment_batch.release`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/treasury/payment-batches/{id}/record-outcome` | Record confirmed/failed/uncertain result | `treasury.payment_batch.record_outcome`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/treasury/payment-batches/{id}/investigate` | Resolve uncertain outcome from evidence | `treasury.payment_batch.investigate`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/bank-reconciliations/{id}/approve` | Approve reconciliation independently | `treasury.bank_reconciliation.approve`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |

Commands are transaction-safe; sensitive/retryable commands require an Idempotency-Key. Return stable error envelopes and redact sensitive fields.
## 11. Frontend responsibilities
Provide a Treasury and Cash Management register, record detail/history, draft form, review/approval queue, and exception view appropriate to the states above. Include scoped search and filters, evidence links, role-specific command availability, validation feedback and stale-version recovery. The server remains authoritative for every action; never show an unconfirmed command as successful.
## 12. Cross-module dependencies and integration events
Consumes AP vouchers; bank gateway; emits PaymentExecuted. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific uncertain payment outcomes, no blind resend and statement reconciliation scenarios.
## 15. Acceptance criteria
All scoped use cases work end-to-end; invalid/out-of-scope actions fail; data ownership is respected; API/UI reflect lifecycle; events are retry-safe; audit is complete; applicable financial invariants hold; tests pass; documentation matches behavior; unresolved policy is not silently assumed.

## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Bank outcome unknown enters pending inquiry and cannot be resubmitted automatically; failed payments require classified reason; unmatched statement lines stay unreconciled.

## Reporting and operational visibility
Cash position, batch queue/status, failed/unknown outcomes, bank statement unmatched items, reconciliation and forecast assumptions.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
