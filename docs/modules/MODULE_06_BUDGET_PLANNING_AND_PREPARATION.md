# MODULE 06 — Budget Planning and Preparation

## 1. Purpose and scope
Calendars, ceilings, departmental estimates, consolidation, review, approved versions. This module owns budget_calendars, versions, ceilings, estimates, submissions, lines. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Calendars, ceilings, departmental estimates, consolidation, review, approved versions. Operational functions are limited to this domain; reference records remain administered by their owning module.

## 3. Actors and responsibilities
Budget officers issue calls and ceilings; departments draft and attest estimates; treasury/finance consolidate; authorized approvers approve the version; departments view only their submissions.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `budget_planning.submission.view` | Domain reader | No | Enforce record and organizational scope. |
| `budget_planning.submission.create` | Domain maker | No | Validate required owner references; maker cannot self-approve. |
| `budget_planning.submission.update_draft` | Assigned record owner | No | Draft-only edits; preserve version and change history. |
| `budget_planning.submission.submit` | Domain maker | No | Validate mandatory fields and evidence before submission. |
| `budget_planning.submission.approve` | Independent delegated approver | Yes | Must differ from maker; authority and organization scope checked server-side. |
| `budget_planning.submission.reject` | Assigned reviewer | No | Require reason; preserve decision history and permit correction only via defined return/resubmit. |
| `budget_planning.submission.export` | Authorized analyst | Conditional for sensitive data | Apply same row scope and field masking as on-screen access. |
## 5. Module-specific business rules
Version snapshots immutable after approval; totals reconcile. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Open calendar/ceilings → departments prepare estimates → submit → consolidate/review → return corrections or approve immutable budget version → publish approved totals.
Initiation, mandatory validation and approval gates are defined above. On failure, Late/incomplete departmental submission is flagged for review; consolidation variance is reported, not silently normalized; rejected version leaves prior approved version active.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Actor / preconditions | Side effects / failure |
|---|---|---|---|---|
| Open calendar/ceilings | departments prepare estimates | departments prepare estimates | Budget officers issue calls and ceilings; departments draft and attest estimates; treasury/finance consolidate; authorized approvers approve the version; departments view only their submissions. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| departments prepare estimates | submit | submit | Budget officers issue calls and ceilings; departments draft and attest estimates; treasury/finance consolidate; authorized approvers approve the version; departments view only their submissions. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| submit | consolidate/review | consolidate/review | Budget officers issue calls and ceilings; departments draft and attest estimates; treasury/finance consolidate; authorized approvers approve the version; departments view only their submissions. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| consolidate/review | return corrections or approve immutable budget version | return corrections or approve immutable budget version | Budget officers issue calls and ceilings; departments draft and attest estimates; treasury/finance consolidate; authorized approvers approve the version; departments view only their submissions. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| return corrections or approve immutable budget version | publish approved totals. | publish approved totals. | Budget officers issue calls and ceilings; departments draft and attest estimates; treasury/finance consolidate; authorized approvers approve the version; departments view only their submissions. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |

No caller may set state directly. Apply idempotency to commands whose retry could create duplicate effects. Financial reversals and period locks are enforced by financial owners (05/07/12/13/14/15 as applicable), not copied into unrelated domains.
## 8. Database ownership and entity relationships
budget_calendars, versions, ceilings, estimates, submissions, lines. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and authorization | Response / errors |
|---|---|---|---|
| POST | `/api/v1/budget-submissions/{id}/return` | Return estimate with reason | `budget_planning.submission.return`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/budget-versions/{id}/publish` | Publish approved immutable version | `budget_planning.version.publish`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |

Commands are transaction-safe; sensitive/retryable commands require an Idempotency-Key. Return stable error envelopes and redact sensitive fields.
## 11. Frontend responsibilities
Provide a Budget Planning and Preparation register, record detail/history, draft form, review/approval queue, and exception view appropriate to the states above. Include scoped search and filters, evidence links, role-specific command availability, validation feedback and stale-version recovery. The server remains authoritative for every action; never show an unconfirmed command as successful.
## 12. Cross-module dependencies and integration events
Consumes org/master/workflow; emits BudgetApproved to 07/22. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
All scoped use cases work end-to-end; invalid/out-of-scope actions fail; data ownership is respected; API/UI reflect lifecycle; events are retry-safe; audit is complete; applicable financial invariants hold; tests pass; documentation matches behavior; unresolved policy is not silently assumed.

## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Late/incomplete departmental submission is flagged for review; consolidation variance is reported, not silently normalized; rejected version leaves prior approved version active.

## Reporting and operational visibility
Submission completeness by unit, ceiling variance, recurrent/development split, consolidation changes, version comparison and approval status.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
