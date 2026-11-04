# MODULE 20 — Debt and Liability Management

## 1. Purpose and scope
Facilities, agreements, repayment schedules, interest and liability register. This module owns debt_facilities, agreements, schedules, interest_events, liabilities. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Facilities, agreements, repayment schedules, interest and liability register. Operational functions are limited to this domain; reference records remain administered by their owning module.

## 3. Actors and responsibilities
Debt officers register facilities and schedules; finance validates accounting; treasury records cash settlement; approvers authorize terms/adjustments; auditors review balances.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `debt.facility.view` | Domain reader | No | Enforce record and organizational scope. |
| `debt.facility.create` | Domain maker | No | Validate required owner references; maker cannot self-approve. |
| `debt.facility.update_draft` | Assigned record owner | No | Draft-only edits; preserve version and change history. |
| `debt.facility.submit` | Domain maker | No | Validate mandatory fields and evidence before submission. |
| `debt.facility.approve` | Independent delegated approver | Yes | Must differ from maker; authority and organization scope checked server-side. |
| `debt.facility.reject` | Assigned reviewer | No | Require reason; preserve decision history and permit correction only via defined return/resubmit. |
| `debt.facility.export` | Authorized analyst | Conditional for sensitive data | Apply same row scope and field masking as on-screen access. |
## 5. Module-specific business rules
Version schedule; reconcile balances; Treasury executes payment, GL posts. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Register facility/agreement → validate approved terms → generate versioned repayment schedule → accrue/record interest and liabilities → reconcile to treasury/GL → track settlements and close facility.
Initiation, mandatory validation and approval gates are defined above. On failure, Schedule mismatch, rate ambiguity or unreconciled balance is flagged for finance; no guessed interest convention; settlement is linked to treasury outcome.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Actor / preconditions | Side effects / failure |
|---|---|---|---|---|
| Register facility/agreement | validate approved terms | validate approved terms | Debt officers register facilities and schedules; finance validates accounting; treasury records cash settlement; approvers authorize terms/adjustments; auditors review balances. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| validate approved terms | generate versioned repayment schedule | generate versioned repayment schedule | Debt officers register facilities and schedules; finance validates accounting; treasury records cash settlement; approvers authorize terms/adjustments; auditors review balances. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| generate versioned repayment schedule | accrue/record interest and liabilities | accrue/record interest and liabilities | Debt officers register facilities and schedules; finance validates accounting; treasury records cash settlement; approvers authorize terms/adjustments; auditors review balances. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| accrue/record interest and liabilities | reconcile to treasury/GL | reconcile to treasury/GL | Debt officers register facilities and schedules; finance validates accounting; treasury records cash settlement; approvers authorize terms/adjustments; auditors review balances. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| reconcile to treasury/GL | track settlements and close facility. | track settlements and close facility. | Debt officers register facilities and schedules; finance validates accounting; treasury records cash settlement; approvers authorize terms/adjustments; auditors review balances. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |

No caller may set state directly. Apply idempotency to commands whose retry could create duplicate effects. Financial reversals and period locks are enforced by financial owners (05/07/12/13/14/15 as applicable), not copied into unrelated domains.
## 8. Database ownership and entity relationships
debt_facilities, agreements, schedules, interest_events, liabilities. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and authorization | Response / errors |
|---|---|---|---|
| POST | `/api/v1/debt/facilities/{id}/approve` | Approve facility under delegated authority | `debt.facility.approve`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/debt/facilities/{id}/schedule-versions` | Propose schedule version | `debt.schedule.create`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/debt/facilities/{id}/accruals` | Prepare controlled accrual input | `debt.accrual.create`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |

Commands are transaction-safe; sensitive/retryable commands require an Idempotency-Key. Return stable error envelopes and redact sensitive fields.
## 11. Frontend responsibilities
Provide a Debt and Liability Management register, record detail/history, draft form, review/approval queue, and exception view appropriate to the states above. Include scoped search and filters, evidence links, role-specific command availability, validation feedback and stale-version recovery. The server remains authoritative for every action; never show an unconfirmed command as successful.
## 12. Cross-module dependencies and integration events
Consumes master/docs; integrates Treasury/GL. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
All scoped use cases work end-to-end; invalid/out-of-scope actions fail; data ownership is respected; API/UI reflect lifecycle; events are retry-safe; audit is complete; applicable financial invariants hold; tests pass; documentation matches behavior; unresolved policy is not silently assumed.

## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Schedule mismatch, rate ambiguity or unreconciled balance is flagged for finance; no guessed interest convention; settlement is linked to treasury outcome.

## Reporting and operational visibility
Principal/interest schedule, maturity ladder, liabilities by type, payment status references and ledger reconciliation.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
