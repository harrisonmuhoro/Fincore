# MODULE 12 — Expenditure Management and Accounts Payable

## 1. Purpose and scope
Invoices, matching, verification, payables, vouchers and credit notes. This module owns supplier_invoices, invoice_lines, matches, vouchers, credit_notes. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Invoices, matching, verification, payables, vouchers and credit notes. Operational functions are limited to this domain; reference records remain administered by their owning module.

## 3. Actors and responsibilities
AP clerks register invoices; invoice verifiers perform match; finance approvers authorize liability/voucher; treasury executes separately; suppliers receive status only.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `ap.invoice.view` | Domain reader | No | Enforce record and organizational scope. |
| `ap.invoice.create` | Domain maker | No | Validate required owner references; maker cannot self-approve. |
| `ap.invoice.update_draft` | Assigned record owner | No | Draft-only edits; preserve version and change history. |
| `ap.invoice.submit` | Domain maker | No | Validate mandatory fields and evidence before submission. |
| `ap.invoice.approve` | Independent delegated approver | Yes | Must differ from maker; authority and organization scope checked server-side. |
| `ap.invoice.reject` | Assigned reviewer | No | Require reason; preserve decision history and permit correction only via defined return/resubmit. |
| `ap.invoice.export` | Authorized analyst | Conditional for sensitive data | Apply same row scope and field masking as on-screen access. |
## 5. Module-specific business rules
Three-way match where applicable; duplicate invoice check; voucher distinct from payment. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Receive invoice → validate supplier/reference/tax/duplicate → match PO, GRN and invoice → resolve variance → verify → prepare/approve voucher → send approved payable to treasury; credit note/reversal linked.
Initiation, mandatory validation and approval gates are defined above. On failure, PO/receipt/invoice mismatch or duplicate invoice blocks verification; disputed invoice remains open with reason; voucher cannot be sent until approved.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Actor / preconditions | Side effects / failure |
|---|---|---|---|---|
| Receive invoice | validate supplier/reference/tax/duplicate | validate supplier/reference/tax/duplicate | AP clerks register invoices; invoice verifiers perform match; finance approvers authorize liability/voucher; treasury executes separately; suppliers receive status only. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| validate supplier/reference/tax/duplicate | match PO, GRN and invoice | match PO, GRN and invoice | AP clerks register invoices; invoice verifiers perform match; finance approvers authorize liability/voucher; treasury executes separately; suppliers receive status only. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| match PO, GRN and invoice | resolve variance | resolve variance | AP clerks register invoices; invoice verifiers perform match; finance approvers authorize liability/voucher; treasury executes separately; suppliers receive status only. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| resolve variance | verify | verify | AP clerks register invoices; invoice verifiers perform match; finance approvers authorize liability/voucher; treasury executes separately; suppliers receive status only. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| verify | prepare/approve voucher | prepare/approve voucher | AP clerks register invoices; invoice verifiers perform match; finance approvers authorize liability/voucher; treasury executes separately; suppliers receive status only. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| prepare/approve voucher | send approved payable to treasury; credit note/reversal linked. | send approved payable to treasury; credit note/reversal linked. | AP clerks register invoices; invoice verifiers perform match; finance approvers authorize liability/voucher; treasury executes separately; suppliers receive status only. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |

No caller may set state directly. Apply idempotency to commands whose retry could create duplicate effects. Financial reversals and period locks are enforced by financial owners (05/07/12/13/14/15 as applicable), not copied into unrelated domains.
## 8. Database ownership and entity relationships
supplier_invoices, invoice_lines, matches, vouchers, credit_notes. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and authorization | Response / errors |
|---|---|---|---|
| POST | `/api/v1/ap/invoices/{id}/match` | Match invoice to order/receipt | `ap.invoice.match`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/ap/invoices/{id}/recognize-liability` | Recognize verified liability | `ap.invoice.recognize_liability`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/ap/vouchers/{id}/send-to-treasury` | Idempotent handoff for payment | `ap.voucher.send_to_treasury`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |

Commands are transaction-safe; sensitive/retryable commands require an Idempotency-Key. Return stable error envelopes and redact sensitive fields.
## 11. Frontend responsibilities
Provide a Expenditure Management and Accounts Payable register, record detail/history, draft form, review/approval queue, and exception view appropriate to the states above. Include scoped search and filters, evidence links, role-specific command availability, validation feedback and stale-version recovery. The server remains authoritative for every action; never show an unconfirmed command as successful.
## 12. Cross-module dependencies and integration events
Consumes supplier, PO, receipt, budget; Treasury executes. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
All scoped use cases work end-to-end; invalid/out-of-scope actions fail; data ownership is respected; API/UI reflect lifecycle; events are retry-safe; audit is complete; applicable financial invariants hold; tests pass; documentation matches behavior; unresolved policy is not silently assumed.

## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
PO/receipt/invoice mismatch or duplicate invoice blocks verification; disputed invoice remains open with reason; voucher cannot be sent until approved.

## Reporting and operational visibility
Invoice aging, matching exceptions, disputed items, approved vouchers, duplicate candidates, supplier balances and payment status reference.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
