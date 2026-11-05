# MODULE 21 — Audit and Compliance Management

## 1. Purpose and scope
Internal audit plans, engagements, findings, responses, corrective action and risk. This module owns audit_plans, engagements, findings, responses, corrective_actions, risks. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Internal audit plans, engagements, findings, responses, corrective action and risk.. Support this workflow: Approve risk-based audit plan → assign independent auditor → collect evidence → issue finding → management response → corrective action tracking → auditor verifies evidence → close/escalate overdue finding.. Reject or route the documented exceptions: Evidence tampering attempt or overdue corrective action escalates; auditee cannot close finding; independent reviewer records closure decision.. Expose the operational views: Plan completion, findings by risk/status, response aging, corrective-action due dates, overdue remediation and independently closed evidence.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Internal auditors plan and execute engagements; auditees respond and own remediation; audit supervisors independently verify closure; system admins cannot alter evidence.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `audit.engagement.create` | Audit planner | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `audit.engagement.assign` | Audit supervisor | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `audit.evidence.add` | Assigned auditor | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `audit.finding.create` | Auditee | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `audit.finding.issue` | Independent auditor | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `audit.response.record` | Independent auditor | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `audit.action.assign` | Independent auditor | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `audit.finding.close` | Independent auditor | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `audit.finding.view` | Assigned auditor | No | Apply organization, record-sensitivity and linked-source scope. |
| `audit.finding.update_draft` | Assigned auditor | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Independent auditors; findings immutable history; closure requires evidence/reviewer.

## 6. Detailed business workflows
Approve risk-based audit plan → assign independent auditor → collect evidence → issue finding → management response → corrective action tracking → auditor verifies evidence → close/escalate overdue finding.
Initiation, mandatory validation and approval gates are defined above. On failure, Evidence tampering attempt or overdue corrective action escalates; auditee cannot close finding; independent reviewer records closure decision.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| plan draft | approve | approved | audit supervisors independently verify closure; risk/independence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| engagement | assign auditor | active | Internal auditors plan and execute engagements; conflict check. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| active | collect evidence | indexed | Internal auditors plan and execute engagements; immutable refs. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| evidence | classify finding | draft | Internal auditors plan and execute engagements; criteria/condition/effect. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| draft | issue | issued | Independent auditor; supervisor. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| issued | response/action | remediation | Internal auditors plan and execute engagements; auditee cannot edit. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| remediation | submit evidence | verification pending | Internal auditors plan and execute engagements; source owner fixes. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| verification | independent verify | closed/reopened | Internal auditors plan and execute engagements; rationale. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
audit_plans, engagements, findings, responses, corrective_actions, risks. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/audit/engagements` | create engagement; validate required fields, source IDs, org scope, state and version. | `audit.engagement.create`; Audit planner; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/audit/engagements/{id}/assign` | assign engagement; validate required fields, source IDs, org scope, state and version. | `audit.engagement.assign`; Audit supervisor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/audit/evidence/{id}/add` | add evidence; validate required fields, source IDs, org scope, state and version. | `audit.evidence.add`; Assigned auditor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/audit/findings` | create finding; validate required fields, source IDs, org scope, state and version. | `audit.finding.create`; Auditee; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/audit/findings/{id}/issue` | issue finding; validate required fields, source IDs, org scope, state and version. | `audit.finding.issue`; Independent auditor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/audit/responses/{id}/record` | record response; validate required fields, source IDs, org scope, state and version. | `audit.response.record`; Independent auditor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/audit/corrective-actions/{id}/assign` | assign action; validate required fields, source IDs, org scope, state and version. | `audit.action.assign`; Independent auditor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/audit/findings/{id}/close` | close finding; validate required fields, source IDs, org scope, state and version. | `audit.finding.close`; Independent auditor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/audit/findings/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `audit.finding.view`; Assigned auditor | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/audit/findings/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `audit.finding.view`; Assigned auditor | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/audit/findings` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `audit.finding.view`; Assigned auditor | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: create, assign, add, issue, record, close. Plan completion, findings by risk/status, response aging, corrective-action due dates, overdue remediation and independently closed evidence. Evidence tampering attempt or overdue corrective action escalates; auditee cannot close finding; independent reviewer records closure decision. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes read-only source evidence; emits AuditFindingRaised/Closed. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence. Preserve working evidence, document versions, message attempts or security telemetry as append-only according to this domain; keep Module 21 audit findings distinct from Module 26 technical incidents.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific auditor independence and evidence immutability scenarios.
## 15. Acceptance criteria
Findings and evidence are protected from auditee edits; remediation closure is independently verified by an auditor who is not the action owner. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Evidence tampering attempt or overdue corrective action escalates; auditee cannot close finding; independent reviewer records closure decision.

## Reporting and operational visibility
Plan completion, findings by risk/status, response aging, corrective-action due dates, overdue remediation and independently closed evidence.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
