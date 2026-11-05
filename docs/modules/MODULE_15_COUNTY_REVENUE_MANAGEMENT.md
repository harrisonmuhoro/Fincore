# MODULE 15 — County Revenue Management

## 1. Purpose and scope
Streams, tariffs, assessments, invoices, receipts, collections, arrears and reconciliation. This module owns revenue_streams, tariffs, assessments, invoices, receipts, allocations. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Streams, tariffs, assessments, invoices, receipts, collections, arrears and reconciliation.. Support this workflow: Configure effective tariff → assess liable account → issue invoice → accept collection callback/cashier receipt idempotently → allocate receipt → arrears follow-up → reconcile collections; void/refund controlled.. Reject or route the documented exceptions: Duplicate callback/receipt is idempotently ignored; void/refund requires reason and approval; unexplained collection variance remains open for reconciliation.. Expose the operational views: Assessment-to-receipt collection, arrears aging, tariff version, collection-channel settlement and unresolved reconciliation differences.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Revenue officers configure approved tariffs and assess; cashiers/collectors record receipts; supervisors void/refund; treasury reconciles settlement; finance consumes accounting result.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `revenue.tariff.activate` | Revenue officer | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `revenue.assessment.create` | Revenue assessor | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `revenue.bill.issue` | Collector | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `revenue.receipt.record` | Revenue accountant | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `revenue.receipt.allocate` | Revenue supervisor | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `revenue.receipt.reverse` | Refund approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `revenue.refund.request` | Refund approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `revenue.refund.approve` | Refund approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `revenue.refund.execute` | Refund approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `revenue.receipt.view` | Revenue officer | No | Apply organization, record-sensitivity and linked-source scope. |
| `revenue.receipt.update_draft` | Revenue officer | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Unique receipts; controlled void/refund; tariff effective dating; callback replay protection. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Configure effective tariff → assess liable account → issue invoice → accept collection callback/cashier receipt idempotently → allocate receipt → arrears follow-up → reconcile collections; void/refund controlled.
Initiation, mandatory validation and approval gates are defined above. On failure, Duplicate callback/receipt is idempotently ignored; void/refund requires reason and approval; unexplained collection variance remains open for reconciliation.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| tariff draft | approve/effectuate | effective | Refund approver; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| case | assess/bill | billed | Refund approver; approved tariff. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| bill | collect | immutable receipt | Refund approver; unique key. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| receipt | allocate | allocated/partial | Revenue supervisor; amount reconciled. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| bill due | age | arrears | Refund approver; approved rules. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| refund | approve | approved | Refund approver; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| approved refund | record Treasury outcome | refunded/failed | Refund approver; execution reference. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| receipt | reverse/adjust | linked correction | Refund approver; reason/original kept. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
revenue_streams, tariffs, assessments, invoices, receipts, allocations. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/revenue/tariffs/{id}/activate` | activate tariff; validate required fields, source IDs, org scope, state and version. | `revenue.tariff.activate`; Revenue officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/revenue/assessments` | create assessment; validate required fields, source IDs, org scope, state and version. | `revenue.assessment.create`; Revenue assessor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/revenue/assessments/{id}/bills` | issue bill; validate required fields, source IDs, org scope, state and version. | `revenue.bill.issue`; Collector; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/revenue/receipts/{id}/record` | record receipt; validate required fields, source IDs, org scope, state and version. | `revenue.receipt.record`; Revenue accountant; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/revenue/receipts/{id}/allocate` | allocate receipt; validate required fields, source IDs, org scope, state and version. | `revenue.receipt.allocate`; Revenue supervisor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/revenue/receipts/{id}/reverse` | reverse receipt; validate required fields, source IDs, org scope, state and version. | `revenue.receipt.reverse`; Refund approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/revenue/refunds` | request refund; validate required fields, source IDs, org scope, state and version. | `revenue.refund.request`; Refund approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/revenue/refunds/{id}/approve` | approve refund; validate required fields, source IDs, org scope, state and version. | `revenue.refund.approve`; Refund approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/revenue/refunds/{id}/execute` | execute refund; validate required fields, source IDs, org scope, state and version. | `revenue.refund.execute`; Refund approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/revenue/receipts` | List scoped records with allowlisted filters and pagination; validate organization scope and record visibility. | `revenue.receipt.view`; Revenue officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/revenue/receipts/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `revenue.receipt.view`; Revenue officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/revenue/receipts/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `revenue.receipt.view`; Revenue officer | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: activate, create, issue, record, allocate, reverse, request, approve, execute. Assessment-to-receipt collection, arrears aging, tariff version, collection-channel settlement and unresolved reconciliation differences. Duplicate callback/receipt is idempotently ignored; void/refund requires reason and approval; unexplained collection variance remains open for reconciliation. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes org/master/payment gateway; emits RevenueReceiptConfirmed. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Receipt issuance is idempotent and immutable; allocations cannot exceed value received; refund approval is independent and refund completion requires Treasury outcome. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Duplicate callback/receipt is idempotently ignored; void/refund requires reason and approval; unexplained collection variance remains open for reconciliation.

## Reporting and operational visibility
Assessment-to-receipt collection, arrears aging, tariff version, collection-channel settlement and unresolved reconciliation differences.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
