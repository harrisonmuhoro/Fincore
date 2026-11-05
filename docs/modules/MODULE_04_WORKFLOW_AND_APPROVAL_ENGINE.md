# MODULE 04 — Workflow and Approval Engine

## 1. Purpose and scope
Reusable workflow definitions, instances, approval decisions, delegation, escalation. This module owns workflow_definitions, steps, instances, decisions, delegations. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Reusable workflow definitions, instances, approval decisions, delegation, escalation.. Support this workflow: Configure versioned workflow → publish → domain submits context → resolve approver set → decisions (approve/return/reject/delegate) → complete/escalate; decision history append-only.. Reject or route the documented exceptions: Missing approver, stale workflow version, overdue stage or unavailable delegate routes to escalation queue; no silent skip; business owner decides cancellation.. Expose the operational views: Queue aging, overdue approvals, delegation activity, rejection/return reasons, SLA breaches and workflow version usage.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Workflow designers configure templates; business requesters initiate instances; named approvers decide; workflow operators reassign/escalate; domain owners authorize cancellation of their submitted transaction.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `workflow.definition.create` | Workflow designer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `workflow.definition.publish` | Workflow owner | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `workflow.instance.assign` | Workflow operator | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `workflow.instance.delegate` | Assigned approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `workflow.instance.approve` | Assigned approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `workflow.instance.return` | Assigned approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `workflow.instance.reject` | Assigned approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `workflow.instance.cancel` | Assigned approver | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `workflow.instance.escalate` | Assigned approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `workflow.workflow_instance.view` | Workflow operator | No | Apply organization, record-sensitivity and linked-source scope. |
| `workflow.workflow_instance.update_draft` | Workflow operator | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
No self-approval where configured; decision history append-only.

## 6. Detailed business workflows
Configure versioned workflow → publish → domain submits context → resolve approver set → decisions (approve/return/reject/delegate) → complete/escalate; decision history append-only.
Initiation, mandatory validation and approval gates are defined above. On failure, Missing approver, stale workflow version, overdue stage or unavailable delegate routes to escalation queue; no silent skip; business owner decides cancellation.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| definition draft | validate/version | validated | Assigned approver; routing and approver resolution. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| validated | publish | active immutable version | Workflow owner; independent owner. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| submitted item | instantiate/pin version | queued | Assigned approver; source reference. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| queued | assign | assigned | Workflow operator; scope checked. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| assigned | approve/return/reject | next/returned/rejected | Assigned approver; reason and no self-approval. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| assigned | delegate/escalate | reassigned/escalated | Assigned approver; bounded authority. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| complete steps | finish | completed | Assigned approver; notify source owner. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| running | cancel | cancelled | Assigned approver; source owner authorization. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
workflow_definitions, steps, instances, decisions, delegations. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/workflow-definitions` | create definition; validate required fields, source IDs, org scope, state and version. | `workflow.definition.create`; Workflow designer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/workflow-definitions/{id}/publish` | publish definition; validate required fields, source IDs, org scope, state and version. | `workflow.definition.publish`; Workflow owner; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/workflow-instances/{id}/assign` | assign instance; validate required fields, source IDs, org scope, state and version. | `workflow.instance.assign`; Workflow operator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/workflow-instances/{id}/delegate` | delegate instance; validate required fields, source IDs, org scope, state and version. | `workflow.instance.delegate`; Assigned approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/workflow-instances/{id}/approve` | approve instance; validate required fields, source IDs, org scope, state and version. | `workflow.instance.approve`; Assigned approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/workflow-instances/{id}/return` | return instance; validate required fields, source IDs, org scope, state and version. | `workflow.instance.return`; Assigned approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/workflow-instances/{id}/reject` | reject instance; validate required fields, source IDs, org scope, state and version. | `workflow.instance.reject`; Assigned approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/workflow-instances/{id}/cancel` | cancel instance; validate required fields, source IDs, org scope, state and version. | `workflow.instance.cancel`; Assigned approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/workflow-instances/{id}/escalate` | escalate instance; validate required fields, source IDs, org scope, state and version. | `workflow.instance.escalate`; Assigned approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/workflow-instances` | List scoped records with allowlisted filters and pagination; validate organization scope and record visibility. | `workflow.workflow_instance.view`; Workflow operator | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/workflow-instances/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `workflow.workflow_instance.view`; Workflow operator | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/workflow-instances/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `workflow.workflow_instance.view`; Workflow operator | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: create, publish, assign, delegate, approve, return, reject, cancel, escalate. Queue aging, overdue approvals, delegation activity, rejection/return reasons, SLA breaches and workflow version usage. Missing approver, stale workflow version, overdue stage or unavailable delegate routes to escalation queue; no silent skip; business owner decides cancellation. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes IAM/org; modules call workflow; emits ApprovalCompleted. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Every running instance stays pinned to its definition version; decisions, delegation, return, rejection, escalation and cancellation are authorized and append-only. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Missing approver, stale workflow version, overdue stage or unavailable delegate routes to escalation queue; no silent skip; business owner decides cancellation.

## Reporting and operational visibility
Queue aging, overdue approvals, delegation activity, rejection/return reasons, SLA breaches and workflow version usage.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
