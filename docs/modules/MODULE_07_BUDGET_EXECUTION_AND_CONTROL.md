# MODULE 07 — Budget Execution and Control

## 1. Purpose and scope
Allocations, releases, availability, commitments, encumbrances, liquidation and adjustments. This module owns allocations, releases, commitments, adjustments, commitment_events. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Allocations, releases, availability, commitments, encumbrances, liquidation and adjustments.. Support this workflow: Load approved budget → allocate/release → request reservation → lock and check available balance → commit/encumber → liquidate/release/adjust with approval and audit.. Reject or route the documented exceptions: Insufficient availability blocks reservation; concurrent requests serialize/one fails; release/liquidation must reference original commitment; override requires configured authority and reason.. Expose the operational views: Available/allocated/released/reserved/committed/liquidated balances, exception aging, utilization by period and commitment lifecycle.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Budget controllers load approved budgets; procurement/AP request reservation or commitment; authorized officers approve adjustment/release; finance monitors exceptions and availability.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `budget_execution.availability.view` | Budget controller | No | Enforce organization, record sensitivity and data-minimization scope. |
| `budget_execution.reservation.create` | Requesting officer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `budget_execution.commitment.approve` | Independent budget approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `budget_execution.commitment.adjust` | Finance reviewer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `budget_execution.commitment.liquidate` | Finance reviewer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `budget_execution.commitment.cancel` | Finance reviewer | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `budget_execution.transfer.approve` | Finance reviewer | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `budget_execution.commitment.view` | Budget controller | No | Apply organization, record-sensitivity and linked-source scope. |
| `budget_execution.commitment.update_draft` | Budget controller | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Lock balance during check; reject overspend; idempotent commitments. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Load approved budget → allocate/release → request reservation → lock and check available balance → commit/encumber → liquidate/release/adjust with approval and audit.
Initiation, mandatory validation and approval gates are defined above. On failure, Insufficient availability blocks reservation; concurrent requests serialize/one fails; release/liquidation must reference original commitment; override requires configured authority and reason.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| approved version | allocate/release | available | Independent budget approver; Module 06 version active. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| available | reserve | reserved | Independent budget approver; atomic locked balance check. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| reserved | approve | committed | Independent budget approver; source request valid and independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| committed | adjust | revised | Finance reviewer; reason and balance recheck. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| committed | liquidate | partly/fully liquidated | Finance reviewer; matched actual. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| reserved/committed | cancel | released/cancelled | Finance reviewer; source authorization. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| transfer request | approve/apply | reallocated | Independent budget approver; atomic two-sided update. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| execution close | close | closed to new commitments | Independent budget approver; balances reconciled. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
allocations, releases, commitments, adjustments, commitment_events. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| GET | `/api/v1/budget-availability` | view availability; validate required fields, source IDs, org scope, state and version. | `budget_execution.availability.view`; Budget controller; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-reservations` | create reservation; validate required fields, source IDs, org scope, state and version. | `budget_execution.reservation.create`; Requesting officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-commitments/{id}/approve` | approve commitment; validate required fields, source IDs, org scope, state and version. | `budget_execution.commitment.approve`; Independent budget approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-commitments/{id}/adjust` | adjust commitment; validate required fields, source IDs, org scope, state and version. | `budget_execution.commitment.adjust`; Finance reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-commitments/{id}/liquidate` | liquidate commitment; validate required fields, source IDs, org scope, state and version. | `budget_execution.commitment.liquidate`; Finance reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-commitments/{id}/cancel` | cancel commitment; validate required fields, source IDs, org scope, state and version. | `budget_execution.commitment.cancel`; Finance reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-transfers/{id}/approve` | approve transfer; validate required fields, source IDs, org scope, state and version. | `budget_execution.transfer.approve`; Finance reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/budget-commitments` | List scoped records with allowlisted filters and pagination; validate organization scope and record visibility. | `budget_execution.commitment.view`; Budget controller | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/budget-commitments/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `budget_execution.commitment.view`; Budget controller | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/budget-commitments/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `budget_execution.commitment.view`; Budget controller | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: view, create, approve, adjust, liquidate, cancel. Available/allocated/released/reserved/committed/liquidated balances, exception aging, utilization by period and commitment lifecycle. Insufficient availability blocks reservation; concurrent requests serialize/one fails; release/liquidation must reference original commitment; override requires configured authority and reason. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes approved budget; serves 09/12; emits CommitmentApproved. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific concurrent availability checks and no overspend scenarios.
## 15. Acceptance criteria
Concurrent reservations cannot overspend available budget; availability, reservations, commitments, liquidation and remaining amounts reconcile under one approved formula. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Insufficient availability blocks reservation; concurrent requests serialize/one fails; release/liquidation must reference original commitment; override requires configured authority and reason.

## Reporting and operational visibility
Available/allocated/released/reserved/committed/liquidated balances, exception aging, utilization by period and commitment lifecycle.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
