# MODULE 09 — Procurement Management

## 1. Purpose and scope
Annual plans, requisitions, tenders, quotations, evaluation, PO and award. This module owns procurement_plans, requisitions, lots, bids, evaluations, purchase_orders. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Annual plans, requisitions, tenders, quotations, evaluation, PO and award.. Support this workflow: Approved plan → requisition and commitment request → sourcing method → invite/receive sealed quotes → evaluate and record rationale → approve award → issue PO; return/reject preserves evaluation trail.. Reject or route the documented exceptions: Conflict declaration or missing bid evidence blocks evaluation/award; no compliant bid follows configurable exception review; process is not auto-awarded.. Expose the operational views: Plan-to-actual status, procurement pipeline, method, bid/evaluation milestones, awards, PO commitments and exception reasons.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Requesting departments create requisitions; procurement officers plan/source; evaluation panel members score with conflict declarations; award authority approves recommendation; suppliers only submit through approved channel.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `procurement.plan.manage` | Procurement officer | No | Privileged scope; changes versioned, reasoned and independently reviewed where high risk. |
| `procurement.requisition.create` | Requesting department | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `procurement.requisition.approve` | Procurement reviewer | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `procurement.solicitation.publish` | Opening panel | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `procurement.bid.open` | Conflict-cleared evaluation panel | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `procurement.evaluation.score` | Award authority | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `procurement.award.approve` | Award authority | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `procurement.purchase_order.issue` | Award authority | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `procurement.process.cancel` | Award authority | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `procurement.requisition.view` | Procurement officer | No | Apply organization, record-sensitivity and linked-source scope. |
| `procurement.requisition.update_draft` | Procurement officer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Evaluation separation; approved commitment before award/order as policy. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Approved plan → requisition and commitment request → sourcing method → invite/receive sealed quotes → evaluate and record rationale → approve award → issue PO; return/reject preserves evaluation trail.
Initiation, mandatory validation and approval gates are defined above. On failure, Conflict declaration or missing bid evidence blocks evaluation/award; no compliant bid follows configurable exception review; process is not auto-awarded.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| plan draft | approve | approved | Procurement reviewer; funding/scope. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| requisition draft | submit/review | approved or returned | Procurement reviewer; specification and budget. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| solicitation draft | publish | open | Opening panel; authorized release. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| open | close/open bids | evaluation | Procurement reviewer; controlled log. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| evaluation | score/recommend | recommended | Procurement reviewer; conflicts/rationale. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| recommended | award | awarded | Procurement reviewer; independent authority. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| awarded | issue PO | issued | Procurement reviewer; supplier active/commitment. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| pre-award | cancel | cancelled | Award authority; reason and preserve bids. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
procurement_plans, requisitions, lots, bids, evaluations, purchase_orders. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| PATCH | `/api/v1/procurement-plans/{id}/manage` | manage plan; validate required fields, source IDs, org scope, state and version. | `procurement.plan.manage`; Procurement officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/requisitions` | create requisition; validate required fields, source IDs, org scope, state and version. | `procurement.requisition.create`; Requesting department; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/requisitions/{id}/approve` | approve requisition; validate required fields, source IDs, org scope, state and version. | `procurement.requisition.approve`; Procurement reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/solicitations/{id}/publish` | publish solicitation; validate required fields, source IDs, org scope, state and version. | `procurement.solicitation.publish`; Opening panel; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/procurement-bids/{id}/open` | open bid; validate required fields, source IDs, org scope, state and version. | `procurement.bid.open`; Conflict-cleared evaluation panel; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/evaluations/{id}/score` | score evaluation; validate required fields, source IDs, org scope, state and version. | `procurement.evaluation.score`; Award authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/awards/{id}/approve` | approve award; validate required fields, source IDs, org scope, state and version. | `procurement.award.approve`; Award authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/purchase-orders/{id}/issue` | issue purchase order; validate required fields, source IDs, org scope, state and version. | `procurement.purchase_order.issue`; Award authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/procurement-processes/{id}/cancel` | cancel process; validate required fields, source IDs, org scope, state and version. | `procurement.process.cancel`; Award authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/requisitions/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `procurement.requisition.view`; Procurement officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/requisitions/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `procurement.requisition.view`; Procurement officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/requisitions` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `procurement.requisition.view`; Procurement officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: manage, create, approve, publish, open, score, issue, cancel. Plan-to-actual status, procurement pipeline, method, bid/evaluation milestones, awards, PO commitments and exception reasons. Conflict declaration or missing bid evidence blocks evaluation/award; no compliant bid follows configurable exception review; process is not auto-awarded. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes suppliers, workflow, budget; publishes PurchaseOrderIssued. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Requisition-to-award preserves sealed bid/opening/evaluation evidence; conflicted evaluators are excluded; PO issuance requires independent award and Module 07 commitment confirmation. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Conflict declaration or missing bid evidence blocks evaluation/award; no compliant bid follows configurable exception review; process is not auto-awarded.

## Reporting and operational visibility
Plan-to-actual status, procurement pipeline, method, bid/evaluation milestones, awards, PO commitments and exception reasons.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
