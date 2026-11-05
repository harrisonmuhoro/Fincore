# MODULE 26 — System Audit, Security and Monitoring

## 1. Purpose and scope
Security events, privileged activity, monitoring incidents, backup/error health, integrity. This module owns security_events, monitoring_incidents, integrity_checks. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Security events, privileged activity, monitoring incidents, backup/error health, integrity.. Support this workflow: Collect security/operational events → detect rule/threshold → create incident → triage/acknowledge → investigate/escalate → resolve with evidence → monitor integrity; never mutates business record.. Reject or route the documented exceptions: Detection false positives can be closed with rationale; confirmed incidents escalate and preserve evidence; monitoring outage triggers operational alert.. Expose the operational views: Authentication failures, privileged activity, incident severity/state, audit-integrity checks, backup freshness and monitoring coverage.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Security operations monitors signals; incident handlers investigate/contain; system admins remediate infrastructure; auditors access protected logs; source module owners remediate business records.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `security.event.view` | Security analyst | No | Enforce organization, record sensitivity and data-minimization scope. |
| `security.alert.triage` | Incident lead | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `security.incident.assign` | Assigned analyst | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `security.incident.investigate` | Authorized responder | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `security.incident.contain` | Independent incident lead | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `security.incident.resolve` | Independent incident lead | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `security.incident.close` | Independent incident lead | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `security.incident.access_review` | Independent incident lead | No | Enforce organization, record sensitivity and data-minimization scope. |
| `security.incident.view` | Security analyst | No | Apply organization, record-sensitivity and linked-source scope. |
| `security.incident.update_draft` | Security analyst | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Append-only evidence; minimize payload; restrict event access.

## 6. Detailed business workflows
Collect security/operational events → detect rule/threshold → create incident → triage/acknowledge → investigate/escalate → resolve with evidence → monitor integrity; never mutates business record.
Initiation, mandatory validation and approval gates are defined above. On failure, Detection false positives can be closed with rationale; confirmed incidents escalate and preserve evidence; monitoring outage triggers operational alert.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| signal | classify/alert | alerted | Security operations monitors signals; source/correlation. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| alerted | triage/assign | incident/false positive | Security operations monitors signals; rationale. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| assigned | investigate | investigating | Authorized responder; evidence preserved. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| investigating | contain | contained | Independent incident lead; business owner unchanged. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| contained | recover | recovering | Security operations monitors signals; integrity checks. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| recovering | resolve | resolved | Independent incident lead; root cause. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| resolved | independent close | closed | Independent incident lead; review. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| closed | new evidence reopen | investigating | Security operations monitors signals; history retained. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
security_events, monitoring_incidents, integrity_checks. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| GET | `/api/v1/security/events` | view event; validate required fields, source IDs, org scope, state and version. | `security.event.view`; Security analyst; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/security/alerts/{id}/triage` | triage alert; validate required fields, source IDs, org scope, state and version. | `security.alert.triage`; Incident lead; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/security/incidents/{id}/assign` | assign incident; validate required fields, source IDs, org scope, state and version. | `security.incident.assign`; Assigned analyst; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/security/incidents/{id}/investigate` | investigate incident; validate required fields, source IDs, org scope, state and version. | `security.incident.investigate`; Authorized responder; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/security/incidents/{id}/contain` | contain incident; validate required fields, source IDs, org scope, state and version. | `security.incident.contain`; Independent incident lead; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/security/incidents/{id}/resolve` | resolve incident; validate required fields, source IDs, org scope, state and version. | `security.incident.resolve`; Independent incident lead; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/security/incidents/{id}/close` | close incident; validate required fields, source IDs, org scope, state and version. | `security.incident.close`; Independent incident lead; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/security/incidents` | access review incident; validate required fields, source IDs, org scope, state and version. | `security.incident.access_review`; Independent incident lead; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/security/incidents/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `security.incident.view`; Security analyst | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/security/incidents/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `security.incident.view`; Security analyst | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: view, triage, assign, investigate, contain, resolve, close, access review. Authentication failures, privileged activity, incident severity/state, audit-integrity checks, backup freshness and monitoring coverage. Detection false positives can be closed with rationale; confirmed incidents escalate and preserve evidence; monitoring outage triggers operational alert. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes IAM/platform telemetry; complement shared audit; not module 21. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence. Preserve working evidence, document versions, message attempts or security telemetry as append-only according to this domain; keep Module 21 audit findings distinct from Module 26 technical incidents.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Security incidents preserve append-only evidence and have triage, containment, recovery and independent closure; they remain distinct from Module 21 audit findings. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Detection false positives can be closed with rationale; confirmed incidents escalate and preserve evidence; monitoring outage triggers operational alert.

## Reporting and operational visibility
Authentication failures, privileged activity, incident severity/state, audit-integrity checks, backup freshness and monitoring coverage.

## Phase 2 delivery status

Append-only module audit events are hash chained per module and checked by `php artisan security:verify-audit-chains`. Authentication and privileged-action telemetry, paginated event/incident APIs, version-checked incident transitions, and authenticated source-reported monitoring snapshots are implemented. IAM's pre-existing access audit table remains its owned access record; Module 26 receives a minimized telemetry copy for monitoring. Snapshots do not run automated backup or infrastructure probes. Automated detection thresholds, organization-specific incident scope, and alert integrations require operational policy/infra contracts and remain deferred. Incident closure requires resolution, a distinct reporter and transition-specific permission; every transition records a reason.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
