# MODULE 19 — Payroll Integration

## 1. Purpose and scope
External payroll import, validation, control totals, mapping, reconciliation and journal request. This module owns payroll_imports, batch_totals, mappings, reconciliation_runs. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: External payroll import, validation, control totals, mapping, reconciliation and journal request.. Support this workflow: Receive payroll batch securely → hash/dedupe → validate totals and mapping → restricted review/reconciliation → request journal → ledger accepts/rejects → retain minimized batch evidence; source payroll unchanged.. Reject or route the documented exceptions: Bad file/schema/control total or duplicate batch is quarantined; import failure is retryable by batch key; PII is redacted from logs; source totals remain unchanged.. Expose the operational views: Batch control totals by period/org, rejected rows, mapping version, duplicate detection, reconciliation status and journal references without exposing unnecessary PII.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Restricted integration operators import batches; payroll/HR validates headcount totals; finance reconciles controls and mappings; GL poster approves resulting journal; employee data is minimized.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `payroll.import_batch.receive` | Integration operator | No | Authenticate source; validate schema/hash and deduplicate before processing. |
| `payroll.import_batch.validate` | Payroll reviewer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `payroll.import_batch.reconcile` | Finance reconciler | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `payroll.import_batch.confirm` | Independent finance confirmer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `payroll.journal_request.send` | Data custodian | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `payroll.import_batch.sensitive_export` | Data custodian | No | Reapply row scope and masking; audit export/download. |
| `payroll.import_batch.view` | Restricted payroll reviewer | No | Apply organization, record-sensitivity and linked-source scope. |
| `payroll.import_batch.update_draft` | Restricted payroll reviewer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Source payroll remains authoritative; minimize personal data; duplicate batch hash blocked. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Receive payroll batch securely → hash/dedupe → validate totals and mapping → restricted review/reconciliation → request journal → ledger accepts/rejects → retain minimized batch evidence; source payroll unchanged.
Initiation, mandatory validation and approval gates are defined above. On failure, Bad file/schema/control total or duplicate batch is quarantined; import failure is retryable by batch key; PII is redacted from logs; source totals remain unchanged.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| received | quarantine/hash | quarantined | Restricted integration operators import batches; private/unique. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| quarantined | validate | valid/exception | Payroll reviewer; schema/totals. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| valid | reconcile | reconciled | Finance reconciler; restricted reviewer. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| reconciled | confirm | confirmed | Independent finance confirmer; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| confirmed | request GL journal | pending | Restricted integration operators import batches; idempotent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| GL response | acknowledge | posted ref/rejected | Restricted integration operators import batches; retain source hash. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| batch | reject/supersede | closed version | Restricted integration operators import batches; no overwrite. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
payroll_imports, batch_totals, mappings, reconciliation_runs. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/payroll/import-batches` | receive import batch; validate required fields, source IDs, org scope, state and version. | `payroll.import_batch.receive`; Integration operator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/payroll/import-batches/{id}/validate` | validate import batch; validate required fields, source IDs, org scope, state and version. | `payroll.import_batch.validate`; Payroll reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/payroll/import-batches/{id}/reconcile` | reconcile import batch; validate required fields, source IDs, org scope, state and version. | `payroll.import_batch.reconcile`; Finance reconciler; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/payroll/import-batches/{id}/confirm` | confirm import batch; validate required fields, source IDs, org scope, state and version. | `payroll.import_batch.confirm`; Independent finance confirmer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/payroll/journal-requests/{id}/send` | send journal request; validate required fields, source IDs, org scope, state and version. | `payroll.journal_request.send`; Data custodian; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/payroll/import-batches/{id}/sensitive-export` | sensitive export import batch; validate required fields, source IDs, org scope, state and version. | `payroll.import_batch.sensitive_export`; Data custodian; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/payroll/import-batches/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `payroll.import_batch.view`; Restricted payroll reviewer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/payroll/import-batches/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `payroll.import_batch.view`; Restricted payroll reviewer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/payroll/import-batches` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `payroll.import_batch.view`; Restricted payroll reviewer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: receive, validate, reconcile, confirm, send, sensitive export. Batch control totals by period/org, rejected rows, mapping version, duplicate detection, reconciliation status and journal references without exposing unnecessary PII. Bad file/schema/control total or duplicate batch is quarantined; import failure is retryable by batch key; PII is redacted from logs; source totals remain unchanged. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes gateway/master/GL; publishes PayrollJournalRequested. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Payroll imports are quarantined, deduplicated, minimized and reconciled to control totals; only confirmed batches send an idempotent journal request to Module 13. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Bad file/schema/control total or duplicate batch is quarantined; import failure is retryable by batch key; PII is redacted from logs; source totals remain unchanged.

## Reporting and operational visibility
Batch control totals by period/org, rejected rows, mapping version, duplicate detection, reconciliation status and journal references without exposing unnecessary PII.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
