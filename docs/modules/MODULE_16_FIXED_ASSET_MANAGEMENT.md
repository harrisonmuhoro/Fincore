# MODULE 16 — Fixed Asset Management

## 1. Purpose and scope
Register, tagging, acquisition, assignment, transfer, maintenance, depreciation, valuation, disposal. This module owns assets, classifications, assignments, maintenance, depreciation_runs, disposals. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Register, tagging, acquisition, assignment, transfer, maintenance, depreciation, valuation, disposal.. Support this workflow: Register/acquire/tag asset → assign custodian/location → transfer or maintain → calculate depreciation under approved policy → verify valuation → approve disposal → record proceeds and accounting request.. Reject or route the documented exceptions: Missing tag or count variance creates exception; depreciation policy gaps block run; disposal cannot close until approval, proceeds and accounting linkage are captured.. Expose the operational views: Register by location/class, custody, acquisition basis, depreciation, physical verification, maintenance, disposal and reconciliation differences.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Asset custodians register/assign; asset accountants capitalize/depreciate; physical verification teams count; independent approvers authorize transfers/disposal; GL consumes accounting intents.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `asset.asset.register` | Asset officer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `asset.asset.assign` | Custodian officer | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `asset.asset_transfer.approve` | Independent asset approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `asset.asset.verify` | Physical verifier | No | Reviewer independent of preparer; retain evidence and variance disposition. |
| `asset.depreciation.prepare` | Asset accountant | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `asset.depreciation.submit` | Disposal authority | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `asset.disposal.approve` | Disposal authority | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `asset.disposal.execute` | Disposal authority | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `asset.asset.retire` | Disposal authority | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `asset.asset.view` | Asset officer | No | Apply organization, record-sensitivity and linked-source scope. |
| `asset.asset.update_draft` | Asset officer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Unique tags; policy-configured depreciation; disposal approval. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Register/acquire/tag asset → assign custodian/location → transfer or maintain → calculate depreciation under approved policy → verify valuation → approve disposal → record proceeds and accounting request.
Initiation, mandatory validation and approval gates are defined above. On failure, Missing tag or count variance creates exception; depreciation policy gaps block run; disposal cannot close until approval, proceeds and accounting linkage are captured.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| acquisition | register/tag | registered/tagged | Independent asset approver; unique tag. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| registered | assign | assigned | Custodian officer; custody accepted. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| transfer | approve/receive | transferred | Independent asset approver; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| active | verify | verified/exception | Physical verifier; physical evidence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| eligible | prepare depreciation | reviewed | Independent asset approver; approved method. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| reviewed | send GL intent | accepted/rejected | Independent asset approver; Module 13 posts. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| asset | approve disposal | approved | Independent asset approver; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| approved | execute/retire | disposed/retired | Independent asset approver; evidence and GL link. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
assets, classifications, assignments, maintenance, depreciation_runs, disposals. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/assets` | register asset; validate required fields, source IDs, org scope, state and version. | `asset.asset.register`; Asset officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/assets/{id}/assign` | assign asset; validate required fields, source IDs, org scope, state and version. | `asset.asset.assign`; Custodian officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/asset-transfer/{id}/approve` | approve asset transfer; validate required fields, source IDs, org scope, state and version. | `asset.asset_transfer.approve`; Independent asset approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/assets/{id}/verify` | verify asset; validate required fields, source IDs, org scope, state and version. | `asset.asset.verify`; Physical verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/asset-depreciation-runs` | prepare depreciation; validate required fields, source IDs, org scope, state and version. | `asset.depreciation.prepare`; Asset accountant; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/asset-depreciation-runs/{id}/submit` | submit depreciation; validate required fields, source IDs, org scope, state and version. | `asset.depreciation.submit`; Disposal authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/asset-disposals/{id}/approve` | approve disposal; validate required fields, source IDs, org scope, state and version. | `asset.disposal.approve`; Disposal authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/asset-disposals/{id}/execute` | execute disposal; validate required fields, source IDs, org scope, state and version. | `asset.disposal.execute`; Disposal authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/assets/{id}/retire` | retire asset; validate required fields, source IDs, org scope, state and version. | `asset.asset.retire`; Disposal authority; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/assets/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `asset.asset.view`; Asset officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/assets/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `asset.asset.view`; Asset officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/assets` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `asset.asset.view`; Asset officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: register, assign, approve, verify, prepare, submit, execute, retire. Register by location/class, custody, acquisition basis, depreciation, physical verification, maintenance, disposal and reconciliation differences. Missing tag or count variance creates exception; depreciation policy gaps block run; disposal cannot close until approval, proceeds and accounting linkage are captured. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes procurement/AP; emits asset accounting intents to GL. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Every asset has a unique tag and custody history; depreciation/disposal produces an accounting intent while only Module 13 can post the journal. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Missing tag or count variance creates exception; depreciation policy gaps block run; disposal cannot close until approval, proceeds and accounting linkage are captured.

## Reporting and operational visibility
Register by location/class, custody, acquisition basis, depreciation, physical verification, maintenance, disposal and reconciliation differences.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
