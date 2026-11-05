# MODULE 10 — Contract Management

## 1. Purpose and scope
Award-linked contracts, amendments, deliverables, milestones, schedules and closure. This module owns contracts, amendments, milestones, deliverables, payment_schedules. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Award-linked contracts, amendments, deliverables, milestones, schedules and closure.. Support this workflow: Import awarded contract → validate parties, values and dates → approve registration → track milestones/deliverables → approve amendment → expiry/closure with outstanding obligations checked.. Reject or route the documented exceptions: Disputed deliverable, expired contract or amendment beyond authorized scope is flagged and blocks downstream certification/payment as configured; retain original signed version.. Expose the operational views: Contract value vs approved amendments, milestone/deliverable status, expiry, obligations and performance exceptions.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Contract officers register approved awards; contract managers track obligations; technical officers certify deliverables; independent approvers approve amendments; AP reads payment schedules.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `contract.contract.register` | Contract manager | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `contract.deliverable.submit` | Technical verifier | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `contract.deliverable.verify` | Contract reviewer | No | Reviewer independent of preparer; retain evidence and variance disposition. |
| `contract.amendment.propose` | Delegated contract approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `contract.amendment.approve` | Delegated contract approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `contract.contract.suspend` | Delegated contract approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `contract.contract.terminate` | Delegated contract approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `contract.contract.close` | Delegated contract approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `contract.contract.view` | Contract manager | No | Apply organization, record-sensitivity and linked-source scope. |
| `contract.contract.update_draft` | Contract manager | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Amendment approval; validate cumulative changes; preserve original award. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Import awarded contract → validate parties, values and dates → approve registration → track milestones/deliverables → approve amendment → expiry/closure with outstanding obligations checked.
Initiation, mandatory validation and approval gates are defined above. On failure, Disputed deliverable, expired contract or amendment beyond authorized scope is flagged and blocks downstream certification/payment as configured; retain original signed version.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| draft | execute | executed | Delegated contract approver; approved award. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| executed | activate | active | Delegated contract approver; effective date. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| active | amend | under review | Delegated contract approver; versioned impact. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| under review | approve | amended | Delegated contract approver; Module 07 approves commitment separately. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| deliverable | verify | accepted/returned | Contract reviewer; technical evidence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| active | terminate/suspend | terminated/suspended | Delegated contract approver; reason. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| obligations resolved | close | closed | Delegated contract approver; checklist complete. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
contracts, amendments, milestones, deliverables, payment_schedules. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/contracts` | register contract; validate required fields, source IDs, org scope, state and version. | `contract.contract.register`; Contract manager; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/contract-deliverables/{id}/submit` | submit deliverable; validate required fields, source IDs, org scope, state and version. | `contract.deliverable.submit`; Technical verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/contract-deliverables/{id}/verify` | verify deliverable; validate required fields, source IDs, org scope, state and version. | `contract.deliverable.verify`; Contract reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/contract-amendments` | propose amendment; validate required fields, source IDs, org scope, state and version. | `contract.amendment.propose`; Delegated contract approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/contract-amendments/{id}/approve` | approve amendment; validate required fields, source IDs, org scope, state and version. | `contract.amendment.approve`; Delegated contract approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/contracts/{id}/suspend` | suspend contract; validate required fields, source IDs, org scope, state and version. | `contract.contract.suspend`; Delegated contract approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/contracts/{id}/terminate` | terminate contract; validate required fields, source IDs, org scope, state and version. | `contract.contract.terminate`; Delegated contract approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/contracts/{id}/close` | close contract; validate required fields, source IDs, org scope, state and version. | `contract.contract.close`; Delegated contract approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/contracts/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `contract.contract.view`; Contract manager | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/contracts/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `contract.contract.view`; Contract manager | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/contracts` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `contract.contract.view`; Contract manager | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: register, submit, verify, propose, approve, suspend, terminate, close. Contract value vs approved amendments, milestone/deliverable status, expiry, obligations and performance exceptions. Disputed deliverable, expired contract or amendment beyond authorized scope is flagged and blocks downstream certification/payment as configured; retain original signed version. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes procurement awards; feeds projects/AP. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
An amendment creates a versioned, independently approved contract change; financial commitment changes are separately authorized by Module 07. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Disputed deliverable, expired contract or amendment beyond authorized scope is flagged and blocks downstream certification/payment as configured; retain original signed version.

## Reporting and operational visibility
Contract value vs approved amendments, milestone/deliverable status, expiry, obligations and performance exceptions.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
