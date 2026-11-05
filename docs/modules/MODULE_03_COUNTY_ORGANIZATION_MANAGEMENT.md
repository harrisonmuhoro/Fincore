# MODULE 03 — County Organization Management

## 1. Purpose and scope
County hierarchy, departments, directorates, sub-counties, wards, cost centres, programme/project identities. This module owns org_units, org_relationships, cost_centres, programmes, project_refs. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: County hierarchy, departments, directorates, sub-counties, wards, cost centres, programme/project identities.. Support this workflow: Propose unit/relationship → validate parent type and no hierarchy cycle → approve/effect-date → active → supersede/archive; referenced nodes cannot be hard deleted.. Reject or route the documented exceptions: Invalid parent/type or hierarchy cycle blocks publication; restructuring preserves old effective hierarchy and requires remapping review for active budgets/users.. Expose the operational views: Effective organization chart, current/historical unit roster, cost-centre mappings and unresolved reorganization references.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
County configuration stewards define units; department heads validate reporting relationships; finance confirms cost-centre use; IAM consumes effective-dated organization assignments.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `org.unit.propose` | Organization steward | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `org.unit.edit_draft` | Department head | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `org.restructure.submit` | Independent organization approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `org.restructure.approve` | Finance cost-centre steward | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `org.leadership.assign` | Finance cost-centre steward | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `org.cost_centre.manage` | Finance cost-centre steward | No | Privileged scope; changes versioned, reasoned and independently reviewed where high risk. |
| `org.history.export` | Finance cost-centre steward | No | Reapply row scope and masking; audit export/download. |
| `org.unit.view` | Organization steward | No | Apply organization, record-sensitivity and linked-source scope. |
| `org.unit.update_draft` | Organization steward | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Effective dating; hierarchy acyclic; referenced units archived not deleted.

## 6. Detailed business workflows
Propose unit/relationship → validate parent type and no hierarchy cycle → approve/effect-date → active → supersede/archive; referenced nodes cannot be hard deleted.
Initiation, mandatory validation and approval gates are defined above. On failure, Invalid parent/type or hierarchy cycle blocks publication; restructuring preserves old effective hierarchy and requires remapping review for active budgets/users.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| draft | validate parent/type/dates | validated | Finance cost-centre steward; acyclic and effective-dated. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| validated | approve/activate | active | Finance cost-centre steward; independent review and impact check. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| active | restructure | superseded plus replacement | Finance cost-centre steward; remap active references. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| superseded | archive | archived | Finance cost-centre steward; no dependants, no hard delete. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
org_units, org_relationships, cost_centres, programmes, project_refs. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/organization/units` | propose unit; validate required fields, source IDs, org scope, state and version. | `org.unit.propose`; Organization steward; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| PATCH | `/api/v1/organization/units/{id}/edit-draft` | edit draft unit; validate required fields, source IDs, org scope, state and version. | `org.unit.edit_draft`; Department head; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/restructure/{id}/submit` | submit restructure; validate required fields, source IDs, org scope, state and version. | `org.restructure.submit`; Independent organization approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/restructure/{id}/approve` | approve restructure; validate required fields, source IDs, org scope, state and version. | `org.restructure.approve`; Finance cost-centre steward; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/organization/leadership/{id}/assign` | assign leadership; validate required fields, source IDs, org scope, state and version. | `org.leadership.assign`; Finance cost-centre steward; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| PATCH | `/api/v1/organization/cost-centres/{id}/manage` | manage cost centre; validate required fields, source IDs, org scope, state and version. | `org.cost_centre.manage`; Finance cost-centre steward; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/history/{id}/export` | export history; validate required fields, source IDs, org scope, state and version. | `org.history.export`; Finance cost-centre steward; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/organization/units/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `org.unit.view`; Organization steward | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/organization/units/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `org.unit.view`; Organization steward | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/organization/units` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `org.unit.view`; Organization steward | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: propose, edit draft, submit, approve, assign, manage, export. Effective organization chart, current/historical unit roster, cost-centre mappings and unresolved reorganization references. Invalid parent/type or hierarchy cycle blocks publication; restructuring preserves old effective hierarchy and requires remapping review for active budgets/users. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumed by IAM and financial domains. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Hierarchy validation rejects cycles and invalid effective-date overlaps; approved restructures retain history and do not delete referenced units. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Invalid parent/type or hierarchy cycle blocks publication; restructuring preserves old effective hierarchy and requires remapping review for active budgets/users.

## Reporting and operational visibility
Effective organization chart, current/historical unit roster, cost-centre mappings and unresolved reorganization references.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
