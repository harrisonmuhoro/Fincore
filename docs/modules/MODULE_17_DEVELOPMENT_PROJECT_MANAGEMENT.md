# MODULE 17 — Development Project Management

## 1. Purpose and scope
Delivery lifecycle, contractors, milestones, progress, certificates and performance. This module owns development_projects, milestones, progress_updates, certificates. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Delivery lifecycle, contractors, milestones, progress, certificates and performance.. Support this workflow: Register project reference → approve charter/budget links → baseline milestones → update progress/evidence → certify works → review variance → close after deliverables; financial actuals remain in budget/GL.. Reject or route the documented exceptions: Milestone delay, certificate dispute or scope variation triggers review; budget/contract changes are requested from owning modules and not silently altered here.. Expose the operational views: Milestone baseline vs actual, certificate status, schedule variance, project risks and expenditure sourced from budget/GL.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Project sponsors propose; appraisal reviewers assess; approving authority baselines; project managers report progress; engineers certify deliverables; finance/procurement maintain their own records.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `project.project.propose` | Project sponsor | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `project.project.appraise` | Project planner | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `project.project.approve` | Project authority | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `project.project_baseline.approve` | Project officer | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `project.progress.update` | Technical verifier | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `project.certificate.verify` | Technical verifier | No | Reviewer independent of preparer; retain evidence and variance disposition. |
| `project.variation.approve` | Technical verifier | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `project.project.close` | Technical verifier | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `project.project.view` | Project officer | No | Apply organization, record-sensitivity and linked-source scope. |
| `project.project.update_draft` | Project officer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Project identity is module 03; budget actuals remain 07/13. Keep policy-dependent rules configurable pending county validation.

## 6. Detailed business workflows
Register project reference → approve charter/budget links → baseline milestones → update progress/evidence → certify works → review variance → close after deliverables; financial actuals remain in budget/GL.
Initiation, mandatory validation and approval gates are defined above. On failure, Milestone delay, certificate dispute or scope variation triggers review; budget/contract changes are requested from owning modules and not silently altered here.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| proposal | appraise | appraised/returned | Project planner; Module 03 identity. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| appraised | approve | approved | Project authority; authority. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| approved | baseline | baselined | Project authority; versioned scope. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| baselined | commence | active | Project authority; funding confirmed. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| active | submit progress/certificate | review | Project authority; evidence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| review | verify/return | verified/returned | Project authority; technical independence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| active | variation | under review | Project authority; impact captured. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| variation | approve | revised baseline | Project authority; Module 07 funds. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| outputs accepted | close | completed | Technical verifier; checklist. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
development_projects, milestones, progress_updates, certificates. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/projects` | propose project; validate required fields, source IDs, org scope, state and version. | `project.project.propose`; Project sponsor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/projects/{id}/appraise` | appraise project; validate required fields, source IDs, org scope, state and version. | `project.project.appraise`; Project planner; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/projects/{id}/approve` | approve project; validate required fields, source IDs, org scope, state and version. | `project.project.approve`; Project authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/project-baseline/{id}/approve` | approve project baseline; validate required fields, source IDs, org scope, state and version. | `project.project_baseline.approve`; Project officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| PATCH | `/api/v1/project-progress/{id}/update` | update progress; validate required fields, source IDs, org scope, state and version. | `project.progress.update`; Technical verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/project-certificates/{id}/verify` | verify certificate; validate required fields, source IDs, org scope, state and version. | `project.certificate.verify`; Technical verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/project-variations/{id}/approve` | approve variation; validate required fields, source IDs, org scope, state and version. | `project.variation.approve`; Technical verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/projects/{id}/close` | close project; validate required fields, source IDs, org scope, state and version. | `project.project.close`; Technical verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/projects/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `project.project.view`; Project officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/projects/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `project.project.view`; Project officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/projects` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `project.project.view`; Project officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: propose, appraise, approve, update, verify, close. Milestone baseline vs actual, certificate status, schedule variance, project risks and expenditure sourced from budget/GL. Milestone delay, certificate dispute or scope variation triggers review; budget/contract changes are requested from owning modules and not silently altered here. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes org/project refs, budget, contracts; reports consume. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Project baselines and variations are versioned; technical evidence is reviewed; project delivery never duplicates budget commitments or ledger actuals. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Milestone delay, certificate dispute or scope variation triggers review; budget/contract changes are requested from owning modules and not silently altered here.

## Reporting and operational visibility
Milestone baseline vs actual, certificate status, schedule variance, project risks and expenditure sourced from budget/GL.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
