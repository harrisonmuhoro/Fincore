# MODULE 14 — Treasury and Cash Management

## 1. Purpose and scope
Bank registry, cashbook, payment batches, execution tracking, statements, reconciliation. This module owns bank_accounts, cashbook_entries, payment_batches, statements, matches. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Bank registry, cashbook, payment batches, execution tracking, statements, reconciliation.. Support this workflow: Build payment batch from approved vouchers → validate beneficiary/bank/duplicates/funding → dual release → submit to bank adapter → record confirmed/failed/unknown → investigate unknown before retry → import statement and reconcile.. Reject or route the documented exceptions: Bank outcome unknown enters pending inquiry and cannot be resubmitted automatically; failed payments require classified reason; unmatched statement lines stay unreconciled.. Expose the operational views: Cash position, batch queue/status, failed/unknown outcomes, bank statement unmatched items, reconciliation and forecast assumptions.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Treasury preparers build batches; independent releasers authorize; execution officers submit; reconcilers match statements; bank adapter reports outcomes; AP cannot execute.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `treasury.payment_batch.create` | Treasury preparer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `treasury.payment_batch.validate` | Treasury controller | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `treasury.payment_batch.approve` | Treasury approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `treasury.payment_batch.release` | Release officer | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `treasury.payment.execute` | Execution operator | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `treasury.payment_outcome.record` | Bank reconciler | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `treasury.payment.investigate` | Bank reconciler | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `treasury.statement.import` | Bank reconciler | No | Authenticate source; validate schema/hash and deduplicate before processing. |
| `treasury.bank_reconciliation.approve` | Bank reconciler | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `treasury.payment_batch.view` | Authorized payment batch reader | No | Enforce organization, record-sensitivity and linked-source scope. |
| `treasury.payment_batch.update_draft` | Assigned payment batch maker | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Idempotent execution; segregate prepare/release/reconcile; uncertain outcome queried first. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Build payment batch from approved vouchers → validate beneficiary/bank/duplicates/funding → dual release → submit to bank adapter → record confirmed/failed/unknown → investigate unknown before retry → import statement and reconcile.
Initiation, mandatory validation and approval gates are defined above. On failure, Bank outcome unknown enters pending inquiry and cannot be resubmitted automatically; failed payments require classified reason; unmatched statement lines stay unreconciled.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| batch draft | validate | valid/rejected | Treasury controller; vouchers/payee/bank/duplicates. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| valid | approve | approved | Treasury approver; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| approved | release | released | Release officer; separate operator. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| released | execute | executing | Execution operator; idempotent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| executing | record result | confirmed/failed/uncertain | Treasury approver; external evidence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| uncertain | investigate | confirmed/failed/pending | Bank reconciler; no blind retry. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| statement | dedupe/match | matched/unmatched | Treasury approver; source retained. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| reconciliation | approve | reconciled | Treasury approver; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| bank active | suspend | inactive | Treasury approver; resolve outstanding. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
bank_accounts, cashbook_entries, payment_batches, statements, matches. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/treasury/payment-batches` | create payment batch; validate required fields, source IDs, org scope, state and version. | `treasury.payment_batch.create`; Treasury preparer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/treasury/payment-batches/{id}/validate` | validate payment batch; validate required fields, source IDs, org scope, state and version. | `treasury.payment_batch.validate`; Treasury controller; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/treasury/payment-batches/{id}/approve` | approve payment batch; validate required fields, source IDs, org scope, state and version. | `treasury.payment_batch.approve`; Treasury approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/treasury/payment-batches/{id}/release` | release payment batch; validate required fields, source IDs, org scope, state and version. | `treasury.payment_batch.release`; Release officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/treasury/payments/{id}/execute` | execute payment; validate required fields, source IDs, org scope, state and version. | `treasury.payment.execute`; Execution operator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/treasury/payment-batches/{id}/outcomes` | record payment outcome; validate required fields, source IDs, org scope, state and version. | `treasury.payment_outcome.record`; Bank reconciler; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/treasury/payments/{id}/investigate` | investigate payment; validate required fields, source IDs, org scope, state and version. | `treasury.payment.investigate`; Bank reconciler; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/treasury/bank-statements` | import statement; validate required fields, source IDs, org scope, state and version. | `treasury.statement.import`; Bank reconciler; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/treasury/bank-reconciliations/{id}/approve` | approve bank reconciliation; validate required fields, source IDs, org scope, state and version. | `treasury.bank_reconciliation.approve`; Bank reconciler; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/treasury/payment-batches/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `treasury.payment_batch.view`; Treasury analyst | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/treasury/payment-batches/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `treasury.payment_batch.view`; Treasury analyst | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/treasury/payment-batches` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `treasury.payment_batch.view`; Treasury analyst | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: create, validate, approve, release, execute, record, investigate, import. Cash position, batch queue/status, failed/unknown outcomes, bank statement unmatched items, reconciliation and forecast assumptions. Bank outcome unknown enters pending inquiry and cannot be resubmitted automatically; failed payments require classified reason; unmatched statement lines stay unreconciled. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes AP vouchers; bank gateway; emits PaymentExecuted. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific uncertain payment outcomes, no blind resend and statement reconciliation scenarios.
## 15. Acceptance criteria
Approval, release and execution are separate; duplicate execution is prevented; uncertain outcomes remain held until evidence/reconciliation resolves them. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Bank outcome unknown enters pending inquiry and cannot be resubmitted automatically; failed payments require classified reason; unmatched statement lines stay unreconciled.

## Reporting and operational visibility
Cash position, batch queue/status, failed/unknown outcomes, bank statement unmatched items, reconciliation and forecast assumptions.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
