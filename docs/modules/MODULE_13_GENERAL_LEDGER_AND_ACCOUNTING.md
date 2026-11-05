# MODULE 13 — General Ledger and Accounting

## 1. Purpose and scope
Journals, double-entry posting, trial balance, reconciliation, adjustments and close. This module owns journals, journal_lines, posting_batches, reconciliations, close_runs. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Journals, double-entry posting, trial balance, reconciliation, adjustments and close.. Support this workflow: Prepare journal → validate accounts, period and source → assert debit=credit → independent approval → post immutable journal; correct via linked reversal/adjustment; period close checks reconciliations then locks.. Reject or route the documented exceptions: Unbalanced journal, inactive combination, duplicate source or closed period blocks posting; failed post rolls back; reversal references original and is balanced.. Expose the operational views: Trial balance, GL detail, journal source trace, subledger reconciliation, unbalanced drafts, close checklist and period status.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Accountants prepare journals/reconciliations; independent reviewers validate; authorized posters post; period controllers close; auditors read immutable ledgers.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `ledger.journal.prepare` | Journal preparer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `ledger.journal.validate` | Ledger reviewer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `ledger.journal.approve` | Independent journal approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `ledger.journal.post` | Authorized poster | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `ledger.journal.reverse` | Adjustment approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `ledger.period.close_readiness` | Adjustment approver | Independent approval or separate authority where the action changes a controlled state | Enforce organization, record sensitivity and data-minimization scope. |
| `ledger.reconciliation.approve` | Adjustment approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `ledger.journal.view` | Journal preparer | No | Apply organization, record-sensitivity and linked-source scope. |
| `ledger.journal.update_draft` | Journal preparer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Balanced before post; posted immutable; reversal link; period lock. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Prepare journal → validate accounts, period and source → assert debit=credit → independent approval → post immutable journal; correct via linked reversal/adjustment; period close checks reconciliations then locks.
Initiation, mandatory validation and approval gates are defined above. On failure, Unbalanced journal, inactive combination, duplicate source or closed period blocks posting; failed post rolls back; reversal references original and is balanced.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| draft journal | validate | valid/returned | Ledger reviewer; balanced, active accounts, source unique, open period. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| valid | approve | approved | Independent journal approver; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| approved | post | posted immutable | Authorized poster; atomic traceable event. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| review | reject/return | rejected/draft | Independent journal approver; reason. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| posted | reverse | linked reversal | Adjustment approver; balanced new journal. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| period open | readiness | evidence returned | Independent journal approver; Module 13 does not own status. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| period close/reopen | Module 05 transition | closed/reopened | Independent journal approver; owner approval/event. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| year end | prepare close evidence | ready | Independent journal approver; Module 05 owns year state. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
journals, journal_lines, posting_batches, reconciliations, close_runs. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/ledger/journals` | prepare journal; validate required fields, source IDs, org scope, state and version. | `ledger.journal.prepare`; Journal preparer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ledger/journals/{id}/validate` | validate journal; validate required fields, source IDs, org scope, state and version. | `ledger.journal.validate`; Ledger reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ledger/journals/{id}/approve` | approve journal; validate required fields, source IDs, org scope, state and version. | `ledger.journal.approve`; Independent journal approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ledger/journals/{id}/post` | post journal; validate required fields, source IDs, org scope, state and version. | `ledger.journal.post`; Authorized poster; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ledger/journals/{id}/reverse` | reverse journal; validate required fields, source IDs, org scope, state and version. | `ledger.journal.reverse`; Adjustment approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/ledger/periods/{id}/close-readiness` | close readiness period; validate required fields, source IDs, org scope, state and version. | `ledger.period.close_readiness`; Adjustment approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ledger/reconciliations/{id}/approve` | approve reconciliation; validate required fields, source IDs, org scope, state and version. | `ledger.reconciliation.approve`; Adjustment approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/ledger/journals/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `ledger.journal.view`; Journal preparer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/ledger/journals/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `ledger.journal.view`; Journal preparer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/ledger/journals` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `ledger.journal.view`; Journal preparer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: prepare, validate, approve, post, reverse, close readiness. Trial balance, GL detail, journal source trace, subledger reconciliation, unbalanced drafts, close checklist and period status. Unbalanced journal, inactive combination, duplicate source or closed period blocks posting; failed post rolls back; reversal references original and is balanced. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes accounting intents; emits JournalPosted/FinancialPeriodClosed. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific balanced journals, immutable posting, reversal links and period lock scenarios.
## 15. Acceptance criteria
Only balanced, source-traceable journals in an open Module 05 period post; posted records cannot be edited/deleted and corrections are linked reversals. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Unbalanced journal, inactive combination, duplicate source or closed period blocks posting; failed post rolls back; reversal references original and is balanced.

## Reporting and operational visibility
Trial balance, GL detail, journal source trace, subledger reconciliation, unbalanced drafts, close checklist and period status.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
