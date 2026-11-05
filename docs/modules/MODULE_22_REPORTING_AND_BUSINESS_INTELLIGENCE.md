# MODULE 22 — Reporting and Business Intelligence

## 1. Purpose and scope
Governed dashboards, reports, schedules, exports and data freshness. This module owns report_definitions, schedules, export_jobs, projection metadata. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Governed dashboards, reports, schedules, exports and data freshness.. Support this workflow: Define governed report → authorize dataset/scope → run against source contracts/read model → attach as-of/freshness metadata → review/export/schedule; no writeback to source transactions.. Reject or route the documented exceptions: Stale or unreconciled source data is visibly labeled; report export blocks unauthorized rows; data-quality variance links to source owner and never writes corrections.. Expose the operational views: Source, as-of time, reconciliation/freshness badge, scoped totals, drill-through to authorized source references and export audit.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Report authors define governed queries; data owners approve definitions; executives/department users consume scoped output; report admins schedule/export; source owners correct source data.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `reporting.definition.create` | Report author | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `reporting.definition.publish` | Data owner | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `reporting.report.run` | Report consumer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `reporting.export.request` | Export approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `reporting.schedule.manage` | Reporting steward | No | Privileged scope; changes versioned, reasoned and independently reviewed where high risk. |
| `reporting.report.administer` | Reporting steward | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `reporting.report.view` | Authorized report consumer | No | Apply organization, record-sensitivity and linked-source scope. |
| `reporting.report.update_draft` | Authorized report consumer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Read-only; scoped access; identify source and as-of timestamp.

## 6. Detailed business workflows
Define governed report → authorize dataset/scope → run against source contracts/read model → attach as-of/freshness metadata → review/export/schedule; no writeback to source transactions.
Initiation, mandatory validation and approval gates are defined above. On failure, Stale or unreconciled source data is visibly labeled; report export blocks unauthorized rows; data-quality variance links to source owner and never writes corrections.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| definition draft | validate | valid | Report authors define governed queries; allowlisted data/scope. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| valid | data owner publish | published immutable | Data owner; version. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| published | request run | queued | Report consumer; scope captured. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| queued | execute | generated/failed | Report authors define governed queries; as-of time. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| generated | request export | pending/ready | Report authors define governed queries; sensitive review. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| schedule active | pause/resume | paused/active | Report authors define governed queries; recheck rights. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
report_definitions, schedules, export_jobs, projection metadata. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/report-definitions` | create definition; validate required fields, source IDs, org scope, state and version. | `reporting.definition.create`; Report author; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/report-definitions/{id}/publish` | publish definition; validate required fields, source IDs, org scope, state and version. | `reporting.definition.publish`; Data owner; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/reports/{id}/run` | run report; validate required fields, source IDs, org scope, state and version. | `reporting.report.run`; Report consumer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/report-exports` | request export; validate required fields, source IDs, org scope, state and version. | `reporting.export.request`; Export approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| PATCH | `/api/v1/report-schedules/{id}/manage` | manage schedule; validate required fields, source IDs, org scope, state and version. | `reporting.schedule.manage`; Reporting steward; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/reports/{id}/administer` | administer report; validate required fields, source IDs, org scope, state and version. | `reporting.report.administer`; Reporting steward; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/reports` | List scoped records with allowlisted filters and pagination; validate organization scope and record visibility. | `reporting.report.view`; Authorized report consumer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/reports/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `reporting.report.view`; Authorized report consumer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/reports/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `reporting.report.view`; Authorized report consumer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: create, publish, run, request, manage, administer. Source, as-of time, reconciliation/freshness badge, scoped totals, drill-through to authorized source references and export audit. Stale or unreconciled source data is visibly labeled; report export blocks unauthorized rows; data-quality variance links to source owner and never writes corrections. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes source contracts; not transaction owner. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Reports are read-only, enforce source row scope at run/export time, and expose data lineage, as-of time and freshness/reconciliation status. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Stale or unreconciled source data is visibly labeled; report export blocks unauthorized rows; data-quality variance links to source owner and never writes corrections.

## Reporting and operational visibility
Source, as-of time, reconciliation/freshness badge, scoped totals, drill-through to authorized source references and export audit.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
