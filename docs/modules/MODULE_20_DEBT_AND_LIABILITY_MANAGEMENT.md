# MODULE 20 — Debt and Liability Management

## 1. Purpose and scope
Facilities, agreements, repayment schedules, interest and liability register. This module owns debt_facilities, agreements, schedules, interest_events, liabilities. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Facilities, agreements, repayment schedules, interest and liability register.. Support this workflow: Register facility/agreement → validate approved terms → generate versioned repayment schedule → accrue/record interest and liabilities → reconcile to treasury/GL → track settlements and close facility.. Reject or route the documented exceptions: Schedule mismatch, rate ambiguity or unreconciled balance is flagged for finance; no guessed interest convention; settlement is linked to treasury outcome.. Expose the operational views: Principal/interest schedule, maturity ladder, liabilities by type, payment status references and ledger reconciliation.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Debt officers register facilities and schedules; finance validates accounting; treasury records cash settlement; approvers authorize terms/adjustments; auditors review balances.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `debt.facility.register` | Debt officer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `debt.facility.approve` | Delegated authority | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `debt.schedule.propose` | Finance approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `debt.schedule.approve` | Debt accountant | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `debt.accrual.prepare` | Debt accountant | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `debt.repayment.request` | Debt accountant | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `debt.facility.close` | Debt accountant | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `debt.facility.view` | Debt officer | No | Apply organization, record-sensitivity and linked-source scope. |
| `debt.facility.update_draft` | Debt officer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Version schedule; reconcile balances; Treasury executes payment, GL posts. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Register facility/agreement → validate approved terms → generate versioned repayment schedule → accrue/record interest and liabilities → reconcile to treasury/GL → track settlements and close facility.
Initiation, mandatory validation and approval gates are defined above. On failure, Schedule mismatch, rate ambiguity or unreconciled balance is flagged for finance; no guessed interest convention; settlement is linked to treasury outcome.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| facility draft | review | under review | Delegated authority; agreement. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| review | approve | active | Delegated authority; delegated. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| active | propose schedule | review | Delegated authority; versioned. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| review | approve schedule | effective | Delegated authority; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| accrual due | send GL intent | pending | Delegated authority; Module 13. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| repayment due | refer Treasury | pending | Delegated authority; not presumed paid. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| outcome | reconcile | reconciled/exception | Delegated authority; Treasury/GL refs. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| settled | close | closed | Debt accountant; no obligations. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
debt_facilities, agreements, schedules, interest_events, liabilities. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/debt/facilities` | register facility; validate required fields, source IDs, org scope, state and version. | `debt.facility.register`; Debt officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/debt/facilities/{id}/approve` | approve facility; validate required fields, source IDs, org scope, state and version. | `debt.facility.approve`; Delegated authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/debt/schedules` | propose schedule; validate required fields, source IDs, org scope, state and version. | `debt.schedule.propose`; Finance approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/debt/schedules/{id}/approve` | approve schedule; validate required fields, source IDs, org scope, state and version. | `debt.schedule.approve`; Debt accountant; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/debt/accruals` | prepare accrual; validate required fields, source IDs, org scope, state and version. | `debt.accrual.prepare`; Debt accountant; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/debt/repayments` | request repayment; validate required fields, source IDs, org scope, state and version. | `debt.repayment.request`; Debt accountant; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/debt/facilities/{id}/close` | close facility; validate required fields, source IDs, org scope, state and version. | `debt.facility.close`; Debt accountant; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/debt/facilities/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `debt.facility.view`; Debt officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/debt/facilities/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `debt.facility.view`; Debt officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/debt/facilities` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `debt.facility.view`; Debt officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: register, approve, propose, prepare, request, close. Principal/interest schedule, maturity ladder, liabilities by type, payment status references and ledger reconciliation. Schedule mismatch, rate ambiguity or unreconciled balance is flagged for finance; no guessed interest convention; settlement is linked to treasury outcome. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes master/docs; integrates Treasury/GL. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Facility and schedule versions are independently approved; repayment status is reconciled with Treasury and GL references before closure. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Schedule mismatch, rate ambiguity or unreconciled balance is flagged for finance; no guessed interest convention; settlement is linked to treasury outcome.

## Reporting and operational visibility
Principal/interest schedule, maturity ladder, liabilities by type, payment status references and ledger reconciliation.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
