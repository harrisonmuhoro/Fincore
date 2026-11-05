# MODULE 24 — Notification and Communication

## 1. Purpose and scope
In-app/email delivery, alerts, announcements, user preferences. This module owns notifications, delivery_attempts, preferences, announcements. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: In-app/email delivery, alerts, announcements, user preferences.. Support this workflow: Subscribe to approved domain event → render template/preferences → queue in-app/email → record attempt → retry/dedupe or dead-letter → user reads/manages preferences; never changes business state.. Reject or route the documented exceptions: Provider failure is retried with deduplication; permanent failure enters dead-letter; business workflow remains unchanged and notification status is distinct.. Expose the operational views: Delivery success/failure by channel/template, retry queue, user preference coverage and announcement history.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Domain events trigger delivery; users manage preferences; communications admins publish announcements; support staff inspect delivery errors but cannot change source transaction decisions.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `notification.notification.view` | Recipient | No | Enforce organization, record sensitivity and data-minimization scope. |
| `notification.preference.manage` | User | No | Privileged scope; changes versioned, reasoned and independently reviewed where high risk. |
| `notification.template.manage` | Notification operator | No | Privileged scope; changes versioned, reasoned and independently reviewed where high risk. |
| `notification.announcement.create` | Communications officer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `notification.announcement.publish` | Communications approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `notification.delivery.retry` | Delivery operator | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `notification.delivery.view` | Support operator | No | Enforce organization, record sensitivity and data-minimization scope. |
| `notification.notification.update_draft` | Recipient | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Delivery failure does not reverse business action; dedupe retries.

## 6. Detailed business workflows
Subscribe to approved domain event → render template/preferences → queue in-app/email → record attempt → retry/dedupe or dead-letter → user reads/manages preferences; never changes business state.
Initiation, mandatory validation and approval gates are defined above. On failure, Provider failure is retried with deduplication; permanent failure enters dead-letter; business workflow remains unchanged and notification status is distinct.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| domain event | dedupe/queue | queued | Domain events trigger delivery; minimal payload. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| queued | deliver | delivered/failed | Domain events trigger delivery; attempt recorded. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| transient failure | retry | queued | Delivery operator; bounded idempotent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| exhausted | dead-letter | dead-lettered | Domain events trigger delivery; operator review. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| announcement draft | approve/publish | published | authorized domain role; independent. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| published | expire | archived | Domain events trigger delivery; history. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| notification | mark read | read | Domain events trigger delivery; recipient only. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
notifications, delivery_attempts, preferences, announcements. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| GET | `/api/v1/notifications` | view notification; validate required fields, source IDs, org scope, state and version. | `notification.notification.view`; Recipient; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| PATCH | `/api/v1/notification-preferences/{id}/manage` | manage preference; validate required fields, source IDs, org scope, state and version. | `notification.preference.manage`; User; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| PATCH | `/api/v1/notification-templates/{id}/manage` | manage template; validate required fields, source IDs, org scope, state and version. | `notification.template.manage`; Notification operator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/announcements` | create announcement; validate required fields, source IDs, org scope, state and version. | `notification.announcement.create`; Communications officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/announcements/{id}/publish` | publish announcement; validate required fields, source IDs, org scope, state and version. | `notification.announcement.publish`; Communications approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/notification-deliveries/{id}/retry` | retry delivery; validate required fields, source IDs, org scope, state and version. | `notification.delivery.retry`; Delivery operator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/notification-deliveries` | view delivery; validate required fields, source IDs, org scope, state and version. | `notification.delivery.view`; Support operator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/notifications/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `notification.notification.view`; Recipient | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/notifications/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `notification.notification.view`; Recipient | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: view, manage, create, publish, retry. Delivery success/failure by channel/template, retry queue, user preference coverage and announcement history. Provider failure is retried with deduplication; permanent failure enters dead-letter; business workflow remains unchanged and notification status is distinct. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes domain/workflow events; delivery only, no approval ownership. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Notification retries are deduplicated and bounded; delivery/read state never changes the source approval or transaction state. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Provider failure is retried with deduplication; permanent failure enters dead-letter; business workflow remains unchanged and notification status is distinct.

## Reporting and operational visibility
Delivery success/failure by channel/template, retry queue, user preference coverage and announcement history.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
