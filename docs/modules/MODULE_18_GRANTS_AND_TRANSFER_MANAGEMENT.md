# MODULE 18 — Grants and Transfer Management

## 1. Purpose and scope
Grant agreements, conditions, allocations, disbursement tracking, utilization/compliance. This module owns grants, conditions, allocations, disbursement_refs, compliance_reports. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Grant agreements, conditions, allocations, disbursement tracking, utilization/compliance.. Support this workflow: Register grant agreement/conditions → validate fund and eligibility → allocate to budget dimensions → record receipts/disbursement references → report utilization/evidence → review compliance → close/return balances per approved policy.. Reject or route the documented exceptions: Condition breach or unsupported expenditure is flagged; unspent/returned funds remain tracked; no assumption that funding is unrestricted.. Expose the operational views: Grant balances/conditions, receipts, allocations, utilization evidence, disbursement references, compliance exceptions and unspent balances.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Grant officers register agreements; programme owners confirm eligible purposes; finance allocates and reconciles; approvers authorize commitments/disbursements; treasury executes cash.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `grant.grant.register` | Grant officer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `grant.grant.activate` | Programme owner | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `grant.allocation.request` | Finance reviewer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `grant.allocation.approve` | Compliance approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `grant.utilization.submit` | Compliance approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `grant.compliance.review` | Compliance approver | No | Enforce organization, record sensitivity and data-minimization scope. |
| `grant.grant.close` | Compliance approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `grant.grant.view` | Grant officer | No | Apply organization, record-sensitivity and linked-source scope. |
| `grant.grant.update_draft` | Grant officer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Restricted-fund eligibility validated; evidence retained; actual payment via Treasury. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Register grant agreement/conditions → validate fund and eligibility → allocate to budget dimensions → record receipts/disbursement references → report utilization/evidence → review compliance → close/return balances per approved policy.
Initiation, mandatory validation and approval gates are defined above. On failure, Condition breach or unsupported expenditure is flagged; unspent/returned funds remain tracked; no assumption that funding is unrestricted.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| candidate | register agreement | due diligence | Compliance approver; terms evidence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| due diligence | approve | active | Compliance approver; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| active | request allocation | pending | Compliance approver; eligible dimensions. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| pending | approve via 07 | allocated | Compliance approver; commitment updated. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| disbursement | refer Treasury | payment pending | Compliance approver; unique ref. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| report due | submit utilization | under review | Compliance approver; evidence/totals. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| review | resolve | accepted/exception | Compliance approver; compliance. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| obligations done | close | closed | Compliance approver; unspent handled per agreement. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
grants, conditions, allocations, disbursement_refs, compliance_reports. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/grants` | register grant; validate required fields, source IDs, org scope, state and version. | `grant.grant.register`; Grant officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/grants/{id}/activate` | activate grant; validate required fields, source IDs, org scope, state and version. | `grant.grant.activate`; Programme owner; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/grant-allocations` | request allocation; validate required fields, source IDs, org scope, state and version. | `grant.allocation.request`; Finance reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/grant-allocations/{id}/approve` | approve allocation; validate required fields, source IDs, org scope, state and version. | `grant.allocation.approve`; Compliance approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/grant-utilization-reports/{id}/submit` | submit utilization; validate required fields, source IDs, org scope, state and version. | `grant.utilization.submit`; Compliance approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/grant-compliance` | review compliance; validate required fields, source IDs, org scope, state and version. | `grant.compliance.review`; Compliance approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/grants/{id}/close` | close grant; validate required fields, source IDs, org scope, state and version. | `grant.grant.close`; Compliance approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/grants/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `grant.grant.view`; Grant officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/grants/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `grant.grant.view`; Grant officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/grants` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `grant.grant.view`; Grant officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: register, activate, request, approve, submit, review, close. Grant balances/conditions, receipts, allocations, utilization evidence, disbursement references, compliance exceptions and unspent balances. Condition breach or unsupported expenditure is flagged; unspent/returned funds remain tracked; no assumption that funding is unrestricted. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes master/budget/workflow; treasury/GL own cash/posting. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Grant allocations respect agreement restrictions and Module 07 controls; disbursement status comes from Treasury; close-out retains utilization and unspent balance evidence. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Condition breach or unsupported expenditure is flagged; unspent/returned funds remain tracked; no assumption that funding is unrestricted.

## Reporting and operational visibility
Grant balances/conditions, receipts, allocations, utilization evidence, disbursement references, compliance exceptions and unspent balances.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
