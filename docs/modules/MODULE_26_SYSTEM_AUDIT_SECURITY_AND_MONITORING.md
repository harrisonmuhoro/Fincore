# MODULE 26 — System Audit, Security and Monitoring

## 1. Purpose and scope
Security events, privileged activity, monitoring incidents, backup/error health, integrity. This module owns security_events, monitoring_incidents, integrity_checks. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Security events, privileged activity, monitoring incidents, backup/error health, integrity. Operational functions are limited to this domain; reference records remain administered by their owning module.

## 3. Actors and responsibilities
Security operations monitors signals; incident handlers investigate/contain; system admins remediate infrastructure; auditors access protected logs; source module owners remediate business records.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `security.incident.view` | Domain reader | No | Enforce record and organizational scope. |
| `security.incident.create` | Domain maker | No | Validate required owner references; maker cannot self-approve. |
| `security.incident.update_draft` | Assigned record owner | No | Draft-only edits; preserve version and change history. |
| `security.incident.submit` | Domain maker | No | Validate mandatory fields and evidence before submission. |
| `security.incident.approve` | Independent delegated approver | Yes | Must differ from maker; authority and organization scope checked server-side. |
| `security.incident.reject` | Assigned reviewer | No | Require reason; preserve decision history and permit correction only via defined return/resubmit. |
| `security.incident.export` | Authorized analyst | Conditional for sensitive data | Apply same row scope and field masking as on-screen access. |
## 5. Module-specific business rules
Append-only evidence; minimize payload; restrict event access. Keep policy-dependent rules configurable pending county validation. Monetary DECIMAL controls apply only to amounts owned by this module.

## 6. Detailed business workflows
Collect security/operational events → detect rule/threshold → create incident → triage/acknowledge → investigate/escalate → resolve with evidence → monitor integrity; never mutates business record.
Initiation, mandatory validation and approval gates are defined above. On failure, Detection false positives can be closed with rationale; confirmed incidents escalate and preserve evidence; monitoring outage triggers operational alert.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Actor / preconditions | Side effects / failure |
|---|---|---|---|---|
| Collect security/operational events | detect rule/threshold | detect rule/threshold | Security operations monitors signals; incident handlers investigate/contain; system admins remediate infrastructure; auditors access protected logs; source module owners remediate business records. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| detect rule/threshold | create incident | create incident | Security operations monitors signals; incident handlers investigate/contain; system admins remediate infrastructure; auditors access protected logs; source module owners remediate business records. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| create incident | triage/acknowledge | triage/acknowledge | Security operations monitors signals; incident handlers investigate/contain; system admins remediate infrastructure; auditors access protected logs; source module owners remediate business records. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| triage/acknowledge | investigate/escalate | investigate/escalate | Security operations monitors signals; incident handlers investigate/contain; system admins remediate infrastructure; auditors access protected logs; source module owners remediate business records. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| investigate/escalate | resolve with evidence | resolve with evidence | Security operations monitors signals; incident handlers investigate/contain; system admins remediate infrastructure; auditors access protected logs; source module owners remediate business records. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |
| resolve with evidence | monitor integrity; never mutates business record. | monitor integrity; never mutates business record. | Security operations monitors signals; incident handlers investigate/contain; system admins remediate infrastructure; auditors access protected logs; source module owners remediate business records. Validate scope, evidence and current version before transition. | Commit state, decision history and audit reference atomically; emit event after commit. On failed prerequisite, preserve source state and return actionable validation/conflict. |

No caller may set state directly. Apply idempotency to commands whose retry could create duplicate effects. Financial reversals and period locks are enforced by financial owners (05/07/12/13/14/15 as applicable), not copied into unrelated domains.
## 8. Database ownership and entity relationships
security_events, monitoring_incidents, integrity_checks. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. DECIMAL for monetary fields; do not cascade-delete financial/audit evidence.

## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and authorization | Response / errors |
|---|---|---|---|
| POST | `/api/v1/security/incidents/{id}/contain` | Record containment action | `security.incident.contain`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/security/incidents/{id}/resolve` | Record resolution evidence | `security.incident.resolve`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |
| POST | `/api/v1/security/incidents/{id}/close` | Independently close incident | `security.incident.close`; check scope, current state, required reason/evidence and maker-checker. | 200/202 with resource state/version and correlation ID; 403/409/422 on authorization, conflict or invalid fields. |

Commands are transaction-safe; sensitive/retryable commands require an Idempotency-Key. Return stable error envelopes and redact sensitive fields.
## 11. Frontend responsibilities
Provide a System Audit, Security and Monitoring register, record detail/history, draft form, review/approval queue, and exception view appropriate to the states above. Include scoped search and filters, evidence links, role-specific command availability, validation feedback and stale-version recovery. The server remains authoritative for every action; never show an unconfirmed command as successful.
## 12. Cross-module dependencies and integration events
Consumes IAM/platform telemetry; complement shared audit; not module 21. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication, Policies/Gates, record/org scope, least privilege and maker-checker server-side. Protect sensitive data and private files. Log privileged actions, approvals, overrides, reversals and imports through canonical audit infrastructure. Preserve immutable audit evidence; do not create competing audit ledgers.

## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
All scoped use cases work end-to-end; invalid/out-of-scope actions fail; data ownership is respected; API/UI reflect lifecycle; events are retry-safe; audit is complete; applicable financial invariants hold; tests pass; documentation matches behavior; unresolved policy is not silently assumed.

## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Detection false positives can be closed with rationale; confirmed incidents escalate and preserve evidence; monitoring outage triggers operational alert.

## Reporting and operational visibility
Authentication failures, privileged activity, incident severity/state, audit-integrity checks, backup freshness and monitoring coverage.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
