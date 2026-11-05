# MODULE 12 — Expenditure Management and Accounts Payable

## 1. Purpose and scope
Invoices, matching, verification, payables, vouchers and credit notes. This module owns supplier_invoices, invoice_lines, matches, vouchers, credit_notes. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Invoices, matching, verification, payables, vouchers and credit notes.. Support this workflow: Receive invoice → validate supplier/reference/tax/duplicate → match PO, GRN and invoice → resolve variance → verify → prepare/approve voucher → send approved payable to treasury; credit note/reversal linked.. Reject or route the documented exceptions: PO/receipt/invoice mismatch or duplicate invoice blocks verification; disputed invoice remains open with reason; voucher cannot be sent until approved.. Expose the operational views: Invoice aging, matching exceptions, disputed items, approved vouchers, duplicate candidates, supplier balances and payment status reference.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
AP clerks register invoices; invoice verifiers perform match; finance approvers authorize liability/voucher; treasury executes separately; suppliers receive status only.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `ap.invoice.register` | AP clerk | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `ap.invoice.match` | Matching clerk | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `ap.invoice.verify` | Independent invoice verifier | No | Reviewer independent of preparer; retain evidence and variance disposition. |
| `ap.liability.recognize` | Finance approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `ap.voucher.prepare` | AP supervisor | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `ap.voucher.approve` | AP supervisor | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `ap.voucher.send_to_treasury` | AP supervisor | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `ap.invoice.credit_note` | AP supervisor | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `ap.invoice.view` | AP clerk | No | Apply organization, record-sensitivity and linked-source scope. |
| `ap.invoice.update_draft` | AP clerk | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Three-way match where applicable; duplicate invoice check; voucher distinct from payment. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Receive invoice → validate supplier/reference/tax/duplicate → match PO, GRN and invoice → resolve variance → verify → prepare/approve voucher → send approved payable to treasury; credit note/reversal linked.
Initiation, mandatory validation and approval gates are defined above. On failure, PO/receipt/invoice mismatch or duplicate invoice blocks verification; disputed invoice remains open with reason; voucher cannot be sent until approved.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| invoice | register/deduplicate | registered | AP supervisor; supplier ref/evidence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| registered | match | matched/exception | Matching clerk; PO/contract/receipt. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| matched | verify | verified | Independent invoice verifier; independent review. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| verified | recognize liability | recognized | AP supervisor; GL intent and open period. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| recognized | prepare voucher | draft | AP supervisor; verified amount. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| draft | approve | approved | AP supervisor; maker-checker. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| approved | handoff | payment pending | AP supervisor; idempotent Treasury ref. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| pending | record outcome | paid/failed/uncertain | AP supervisor; Treasury evidence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |
| invoice | credit/reject/cancel | adjusted/rejected/cancelled | AP supervisor; linked history. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit correction/reversal/investigation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
supplier_invoices, invoice_lines, matches, vouchers, credit_notes. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/ap/invoices` | register invoice; validate required fields, source IDs, org scope, state and version. | `ap.invoice.register`; AP clerk; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ap/invoices/{id}/match` | match invoice; validate required fields, source IDs, org scope, state and version. | `ap.invoice.match`; Matching clerk; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ap/invoices/{id}/verify` | verify invoice; validate required fields, source IDs, org scope, state and version. | `ap.invoice.verify`; Independent invoice verifier; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ap/liabilities/{id}/recognize` | recognize liability; validate required fields, source IDs, org scope, state and version. | `ap.liability.recognize`; Finance approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ap/payment-vouchers` | prepare voucher; validate required fields, source IDs, org scope, state and version. | `ap.voucher.prepare`; AP supervisor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ap/payment-vouchers/{id}/approve` | approve voucher; validate required fields, source IDs, org scope, state and version. | `ap.voucher.approve`; AP supervisor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/ap/payment-vouchers/{id}/send-to-treasury` | send to treasury voucher; validate required fields, source IDs, org scope, state and version. | `ap.voucher.send_to_treasury`; AP supervisor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| PATCH | `/api/v1/ap/invoices/{id}/credit-note` | credit note invoice; validate required fields, source IDs, org scope, state and version. | `ap.invoice.credit_note`; AP supervisor; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/ap/invoices/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `ap.invoice.view`; AP clerk | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/ap/invoices/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `ap.invoice.view`; AP clerk | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/ap/invoices` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `ap.invoice.view`; AP clerk | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: register, match, verify, recognize, prepare, approve, send to treasury, credit note. Invoice aging, matching exceptions, disputed items, approved vouchers, duplicate candidates, supplier balances and payment status reference. PO/receipt/invoice mismatch or duplicate invoice blocks verification; disputed invoice remains open with reason; voucher cannot be sent until approved. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes supplier, PO, receipt, budget; Treasury executes. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Duplicate invoices are blocked; matching, liability recognition, voucher approval and Treasury handoff are distinct, traceable states; voucher approval never marks payment executed. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
PO/receipt/invoice mismatch or duplicate invoice blocks verification; disputed invoice remains open with reason; voucher cannot be sent until approved.

## Reporting and operational visibility
Invoice aging, matching exceptions, disputed items, approved vouchers, duplicate candidates, supplier balances and payment status reference.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
