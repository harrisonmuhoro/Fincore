# MODULE 11 — Inventory and Stores Management

## 1. Purpose and scope
Items, stores, goods receipt, issues, transfers, counts and valuation. This module owns items, stores, receipts, receipt_lines, stock_movements, stocktakes. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Items, stores, goods receipt, issues, transfers, counts and valuation.. Support this workflow: PO receipt → inspect quantities/condition → post GRN and stock movement; issue/transfer/count/adjust each creates immutable movement, exceptions require review/approval.. Reject or route the documented exceptions: Inspection variance quarantines receipt; issue below available quantity blocks; count variance routes for approval; damaged/obsolete stock stays separately identifiable.. Expose the operational views: On-hand/reserved/available quantities, valuation, receipt/issue/transfer trace, stocktake variance, reorder and damaged/obsolete items.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Storekeepers inspect receipts; custodians request issues; stock controllers approve adjustments/count variances; procurement sends PO references; AP matches GRN evidence.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `inventory.receipt.record` | Storekeeper | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `inventory.receipt.inspect` | Receiving inspector | No | Reviewer independent of preparer; retain evidence and variance disposition. |
| `inventory.stock.issue` | Store manager | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `inventory.stock.transfer` | Stock controller | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `inventory.stock_count.submit` | Stock controller | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `inventory.stock_adjustment.approve` | Stock controller | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `inventory.receipt.view` | Storekeeper | No | Apply organization, record-sensitivity and linked-source scope. |
| `inventory.receipt.update_draft` | Storekeeper | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
No negative stock absent approved adjustment; movement ledger immutable. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
PO receipt → inspect quantities/condition → post GRN and stock movement; issue/transfer/count/adjust each creates immutable movement, exceptions require review/approval.
Initiation, mandatory validation and approval gates are defined above. On failure, Inspection variance quarantines receipt; issue below available quantity blocks; count variance routes for approval; damaged/obsolete stock stays separately identifiable.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| delivery | receive | awaiting inspection | Stock controller; PO link. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| awaiting inspection | inspect | accepted/rejected | Receiving inspector; independent evidence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| accepted | put away | available | Stock controller; location/lot. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| available | issue/transfer | issued/in transit | Stock controller; lock quantity. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| in transit | confirm | available destination | Stock controller; destination count. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| count | submit | variance pending | Stock controller; snapshot. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| variance | approve adjustment | adjusted | Stock controller; append movement. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
items, stores, receipts, receipt_lines, stock_movements, stocktakes. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/inventory/receipts/{id}/record` | record receipt; validate required fields, source IDs, org scope, state and version. | `inventory.receipt.record`; Storekeeper; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/inventory/receipts/{id}/inspect` | inspect receipt; validate required fields, source IDs, org scope, state and version. | `inventory.receipt.inspect`; Receiving inspector; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/inventory/stock/{id}/issue` | issue stock; validate required fields, source IDs, org scope, state and version. | `inventory.stock.issue`; Store manager; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/inventory/stock/{id}/transfer` | transfer stock; validate required fields, source IDs, org scope, state and version. | `inventory.stock.transfer`; Stock controller; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/inventory/stock-counts/{id}/submit` | submit stock count; validate required fields, source IDs, org scope, state and version. | `inventory.stock_count.submit`; Stock controller; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/inventory/adjustments/{id}/approve` | approve stock adjustment; validate required fields, source IDs, org scope, state and version. | `inventory.stock_adjustment.approve`; Stock controller; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/inventory/receipts` | List scoped records with allowlisted filters and pagination; validate organization scope and record visibility. | `inventory.receipt.view`; Storekeeper | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/inventory/receipts/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `inventory.receipt.view`; Storekeeper | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/inventory/receipts/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `inventory.receipt.view`; Storekeeper | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: record, inspect, issue, transfer, submit, approve. On-hand/reserved/available quantities, valuation, receipt/issue/transfer trace, stocktake variance, reorder and damaged/obsolete items. Inspection variance quarantines receipt; issue below available quantity blocks; count variance routes for approval; damaged/obsolete stock stays separately identifiable. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes PO; emits GoodsReceived to AP/ledger. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Receipt acceptance is independently evidenced; concurrent issues cannot create negative stock; adjustments are append-only and independently approved. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Inspection variance quarantines receipt; issue below available quantity blocks; count variance routes for approval; damaged/obsolete stock stays separately identifiable.

## Reporting and operational visibility
On-hand/reserved/available quantities, valuation, receipt/issue/transfer trace, stocktake variance, reorder and damaged/obsolete items.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
