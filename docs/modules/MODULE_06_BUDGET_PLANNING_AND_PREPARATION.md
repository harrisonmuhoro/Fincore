# MODULE 06 — Budget Planning and Preparation

## 1. Purpose and scope
Calendars, ceilings, departmental estimates, consolidation, review, approved versions. This module owns budget_calendars, versions, ceilings, estimates, submissions, lines. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Calendars, ceilings, departmental estimates, consolidation, review, approved versions.. Support this workflow: Open calendar/ceilings → departments prepare estimates → submit → consolidate/review → return corrections or approve immutable budget version → publish approved totals.. Reject or route the documented exceptions: Late/incomplete departmental submission is flagged for review; consolidation variance is reported, not silently normalized; rejected version leaves prior approved version active.. Expose the operational views: Submission completeness by unit, ceiling variance, recurrent/development split, consolidation changes, version comparison and approval status.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Budget officers issue calls and ceilings; departments draft and attest estimates; treasury/finance consolidate; authorized approvers approve the version; departments view only their submissions.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `budget_planning.call.manage` | Budget officer | No | Privileged scope; changes versioned, reasoned and independently reviewed where high risk. |
| `budget_planning.estimate.create` | Department planner | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `budget_planning.submission.submit` | Finance consolidator | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `budget_planning.submission.review` | Budget approver | No | Enforce organization, record sensitivity and data-minimization scope. |
| `budget_planning.submission.return` | Budget approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `budget_planning.version.approve` | Budget approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `budget_planning.version.publish` | Budget approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `budget_planning.submission.view` | Department planner | No | Apply organization, record-sensitivity and linked-source scope. |
| `budget_planning.submission.update_draft` | Department planner | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Version snapshots immutable after approval; totals reconcile. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Open calendar/ceilings → departments prepare estimates → submit → consolidate/review → return corrections or approve immutable budget version → publish approved totals.
Initiation, mandatory validation and approval gates are defined above. On failure, Late/incomplete departmental submission is flagged for review; consolidation variance is reported, not silently normalized; rejected version leaves prior approved version active.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| call draft | issue | open | Budget approver; active year and ceilings. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| estimate draft | submit | submitted | Finance consolidator; attestation/totals valid. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| submitted | review | under review | Budget approver; finance check. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| review | return | returned | Budget approver; reason retained. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| returned | resubmit | submitted new version | Budget approver; revalidate. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| consolidated | approve | approved | Budget approver; independent authority. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| approved | publish | immutable published version | Budget approver; Module 07 receives. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
budget_calendars, versions, ceilings, estimates, submissions, lines. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| PATCH | `/api/v1/budget-calls/{id}/manage` | manage call; validate required fields, source IDs, org scope, state and version. | `budget_planning.call.manage`; Budget officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-estimates` | create estimate; validate required fields, source IDs, org scope, state and version. | `budget_planning.estimate.create`; Department planner; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-submissions/{id}/submit` | submit submission; validate required fields, source IDs, org scope, state and version. | `budget_planning.submission.submit`; Finance consolidator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/budget-submissions` | review submission; validate required fields, source IDs, org scope, state and version. | `budget_planning.submission.review`; Budget approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-submissions/{id}/return` | return submission; validate required fields, source IDs, org scope, state and version. | `budget_planning.submission.return`; Budget approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-versions/{id}/approve` | approve version; validate required fields, source IDs, org scope, state and version. | `budget_planning.version.approve`; Budget approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/budget-versions/{id}/publish` | publish version; validate required fields, source IDs, org scope, state and version. | `budget_planning.version.publish`; Budget approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/budget-submissions/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `budget_planning.submission.view`; Department planner | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/budget-submissions/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `budget_planning.submission.view`; Department planner | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: manage, create, submit, review, return, approve, publish. Submission completeness by unit, ceiling variance, recurrent/development split, consolidation changes, version comparison and approval status. Late/incomplete departmental submission is flagged for review; consolidation variance is reported, not silently normalized; rejected version leaves prior approved version active. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes org/master/workflow; emits BudgetApproved to 07/22. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
A published approved budget version is immutable and uniquely versioned; totals reconcile to submitted lines and Module 07 receives its exact version reference. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Late/incomplete departmental submission is flagged for review; consolidation variance is reported, not silently normalized; rejected version leaves prior approved version active.

## Reporting and operational visibility
Submission completeness by unit, ceiling variance, recurrent/development split, consolidation changes, version comparison and approval status.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
