# MODULE 08 — Supplier Management

## 1. Purpose and scope
Supplier profile, verification, documents, status and restricted bank details. This module owns suppliers, classifications, verifications, bank_accounts, performance. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Supplier profile, verification, documents, status and restricted bank details.. Support this workflow: Register supplier → validate identity and duplicates → verify documents/tax/bank separately → approve/activate → update by independently verified change; suspend blocks new awards.. Reject or route the documented exceptions: Duplicate supplier or mismatched bank ownership enters verification hold; suspended supplier blocked for new sourcing; bank change never overwrites historical account evidence.. Expose the operational views: Verification status, expiring documents, duplicate candidates, bank-change history, supplier suspension and performance evidence.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Supplier registry officers review registration; independent due-diligence officers verify documents; restricted bank-data reviewers approve account changes; procurement/AP consume supplier status.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `supplier.supplier.register` | Supplier registry officer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `supplier.supplier.verify` | Due-diligence reviewer | No | Reviewer independent of preparer; retain evidence and variance disposition. |
| `supplier.supplier.activate` | Supplier approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `supplier.supplier.suspend` | Bank-data verifier | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `supplier.bank_details.propose` | Bank-data verifier | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `supplier.bank_details.approve` | Bank-data verifier | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `supplier.performance.view` | Bank-data verifier | No | Enforce organization, record sensitivity and data-minimization scope. |
| `supplier.supplier.view` | Supplier registry officer | No | Apply organization, record-sensitivity and linked-source scope. |
| `supplier.supplier.update_draft` | Supplier registry officer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Independent bank change verification; duplicate review; inactive supplier blocked for new awards.

## 6. Detailed business workflows
Register supplier → validate identity and duplicates → verify documents/tax/bank separately → approve/activate → update by independently verified change; suspend blocks new awards.
Initiation, mandatory validation and approval gates are defined above. On failure, Duplicate supplier or mismatched bank ownership enters verification hold; suspended supplier blocked for new sourcing; bank change never overwrites historical account evidence.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| draft | submit | submitted | Bank-data verifier; identity supplied. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| submitted | verify | verified or hold | Due-diligence reviewer; duplicate/evidence check. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| verified | activate | active | Supplier approver; bank verified separately. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| active | propose bank change | pending | Bank-data verifier; new protected version. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| pending | independently verify | effective | Due-diligence reviewer; maker distinct. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| active | suspend | suspended | Bank-data verifier; reason and consumers notified. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| suspended | reinstate | active | Bank-data verifier; issues cleared. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
suppliers, classifications, verifications, bank_accounts, performance. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/suppliers` | register supplier; validate required fields, source IDs, org scope, state and version. | `supplier.supplier.register`; Supplier registry officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/suppliers/{id}/verify` | verify supplier; validate required fields, source IDs, org scope, state and version. | `supplier.supplier.verify`; Due-diligence reviewer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/suppliers/{id}/activate` | activate supplier; validate required fields, source IDs, org scope, state and version. | `supplier.supplier.activate`; Supplier approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/suppliers/{id}/suspend` | suspend supplier; validate required fields, source IDs, org scope, state and version. | `supplier.supplier.suspend`; Bank-data verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/supplier-bank-detail-changes` | propose bank details; validate required fields, source IDs, org scope, state and version. | `supplier.bank_details.propose`; Bank-data verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/supplier-bank-detail-changes/{id}/approve` | approve bank details; validate required fields, source IDs, org scope, state and version. | `supplier.bank_details.approve`; Bank-data verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/supplier-performance` | view performance; validate required fields, source IDs, org scope, state and version. | `supplier.performance.view`; Bank-data verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/suppliers/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `supplier.supplier.view`; Supplier registry officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/suppliers/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `supplier.supplier.view`; Supplier registry officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/suppliers` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `supplier.supplier.view`; Supplier registry officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: register, verify, activate, suspend, propose, approve, view. Verification status, expiring documents, duplicate candidates, bank-change history, supplier suspension and performance evidence. Duplicate supplier or mismatched bank ownership enters verification hold; suspended supplier blocked for new sourcing; bank change never overwrites historical account evidence. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumed by procurement/contracts/AP. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Supplier activation requires verified evidence; bank-detail changes require independent verification, preserve prior versions and are masked for unauthorized roles. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Duplicate supplier or mismatched bank ownership enters verification hold; suspended supplier blocked for new sourcing; bank change never overwrites historical account evidence.

## Reporting and operational visibility
Verification status, expiring documents, duplicate candidates, bank-change history, supplier suspension and performance evidence.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
