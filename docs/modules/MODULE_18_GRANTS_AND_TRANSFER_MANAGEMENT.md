# MODULE 18 — Grants and Transfer Management

## 1. Purpose and scope
Grant agreements, conditions, allocations, disbursement tracking, utilization/compliance. This module owns grants, conditions, allocations, disbursement_refs, compliance_reports. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Grant agreements, conditions, allocations, disbursement tracking, utilization/compliance. Operational functions are limited to this domain; reference records remain administered by their owning module.

## 3. Actors and responsibilities
Grant officers register agreements; programme owners confirm eligible purposes; finance allocates and reconciles; approvers authorize commitments/disbursements; treasury executes cash.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `grant.grant.view` | Domain reader | No | Enforce record and organizational scope. |
| `grant.grant.create` | Domain maker | No | Validate required owner references; maker cannot self-approve. |
| `grant.grant.update_draft` | Assigned record owner | No | Draft-only edits; preserve version and change history. |
| `grant.grant.submit` | Domain maker | No | Validate mandatory fields and evidence before submission. |
| `grant.grant.approve` | Independent delegated approver | Yes | Must differ from maker; authority and organization scope checked server-side. |
| `grant.grant.reject` | Assigned reviewer | No | Require reason; preserve decision history and permit correction only via defined return/resubmit. |
| `grant.grant.export` | Authorized analyst | Conditional for sensitive data | Apply same row scope and field masking as on-screen access. |
## 5. Module-specific business rules
Restricted-fund eligibility validated; evidence retained; actual payment via Treasury. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Register grant agreement/conditions → validate fund and eligibility → allocate to budget dimensions → record receipts/disbursement references → report utilization/evidence → review compliance → close/return balances per approved policy.
Initiation, mandatory validation and approval gates are defined above. On failure, Condition breach or unsupported expenditure is flagged; unspent/returned funds remain tracked; no assumption that funding is unrestricted.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Actor / preconditions | Side effects / failure |
|---|---|---|---|---|
| Register grant agreement/conditions | validate fund and eligibility | validate fund and eligibility | Grant officers register agreements; programme owners confirm eligible purposes; finance allocates and reconciles; approvers authorize commitments/disbursements; treasury executes cash. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| validate fund and eligibility | allocate to budget dimensions | allocate to budget dimensions | Grant officers register agreements; programme owners confirm eligible purposes; finance allocates and reconciles; approvers authorize commitments/disbursements; treasury executes cash. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| allocate to budget dimensions | record receipts/disbursement references | record receipts/disbursement references | Grant officers register agreements; programme owners confirm eligible purposes; finance allocates and reconciles; approvers authorize commitments/disbursements; treasury executes cash. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| record receipts/disbursement references | report utilization/evidence | report utilization/evidence | Grant officers register agreements; programme owners confirm eligible purposes; finance allocates and reconciles; approvers authorize commitments/disbursements; treasury executes cash. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| report utilization/evidence | review compliance | review compliance | Grant officers register agreements; programme owners confirm eligible purposes; finance allocates and reconciles; approvers authorize commitments/disbursements; treasury executes cash. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| review compliance | close/return balances per approved policy. | close/return balances per approved policy. | Grant officers register agreements; programme owners confirm eligible purposes; finance allocates and reconciles; approvers authorize commitments/disbursements; treasury executes cash. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |

No caller may set state directly. Apply idempotency to commands whose retry could create duplicate effects. Financial reversals and period locks are enforced by financial owners (05/07/12/13/14/15 as applicable), not copied into unrelated domains.
## 8. Database ownership and entity relationships
grants, conditions, allocations, disbursement_refs, compliance_reports. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and authorization | Response / errors |
|---|---|---|---|
| POST | `/api/v1/grants/{id}/allocations` | Propose restricted allocation | `grant.allocation.create`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/grant-allocations/{id}/approve` | Approve eligible allocation | `grant.allocation.approve`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/grants/{id}/utilization-reports` | Submit utilization evidence | `grant.utilization.submit`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |

Commands are transaction-safe; sensitive/retryable commands require an Idempotency-Key. Return stable error envelopes and redact sensitive fields.
## 11. Frontend responsibilities
Provide a Grants and Transfer Management register, record detail/history, draft form, review/approval queue, and exception view appropriate to the states above. Include scoped search and filters, evidence links, role-specific command availability, validation feedback and stale-version recovery. The server remains authoritative for every action; never show an unconfirmed command as successful.
## 12. Cross-module dependencies and integration events
Consumes master/budget/workflow; treasury/GL own cash/posting. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
All scoped use cases work end-to-end; invalid/out-of-scope actions fail; data ownership is respected; API/UI reflect lifecycle; events are retry-safe; audit is complete; applicable financial invariants hold; tests pass; documentation matches behavior; unresolved policy is not silently assumed.

## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Condition breach or unsupported expenditure is flagged; unspent/returned funds remain tracked; no assumption that funding is unrestricted.

## Reporting and operational visibility
Grant balances/conditions, receipts, allocations, utilization evidence, disbursement references, compliance exceptions and unspent balances.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
