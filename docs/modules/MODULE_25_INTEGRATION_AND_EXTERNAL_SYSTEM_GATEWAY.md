# MODULE 25 — Integration and External System Gateway

## 1. Purpose and scope
Adapters, credentials refs, webhook ingress, retries, monitoring and transport audit. This module owns integration_connections, messages, webhooks, attempts, incidents. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Adapters, credentials refs, webhook ingress, retries, monitoring and transport audit.. Support this workflow: Register adapter contract/credentials reference → test connection → accept signed/replay-safe request → normalize/enqueue → retry with backoff → reconcile outcome/dead-letter; domain owner decides transaction result.. Reject or route the documented exceptions: Signature/replay/schema failure is rejected and logged; timeouts use bounded retry; uncertain transaction results require source-domain reconciliation, not duplicate submission.. Expose the operational views: Adapter health, queued/sent/failed/dead-letter messages, retry counts, reconciliation status and credential expiry metadata (never secret values).. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Integration operators configure connector metadata; security admins control credentials; domain owners approve payload contracts; support operators retry/reconcile failed transport, not business outcome.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `integration.connector.view` | Integration operator | No | Enforce organization, record sensitivity and data-minimization scope. |
| `integration.connector.manage` | Security credential steward | No | Privileged scope; changes versioned, reasoned and independently reviewed where high risk. |
| `integration.message.view` | Support investigator | No | Enforce organization, record sensitivity and data-minimization scope. |
| `integration.message.retry` | Domain owner | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `integration.message.replay` | Independent replay approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `integration.message.reconcile` | Independent replay approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `integration.credential.rotate` | Independent replay approver | Independent approval or separate authority where the action changes a controlled state | Privileged scope; changes versioned, reasoned and independently reviewed where high risk. |
| `integration.message.update_draft` | Integration operator | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Mock until authorized specs; signature/replay validation; secrets never returned.

## 6. Detailed business workflows
Register adapter contract/credentials reference → test connection → accept signed/replay-safe request → normalize/enqueue → retry with backoff → reconcile outcome/dead-letter; domain owner decides transaction result.
Initiation, mandatory validation and approval gates are defined above. On failure, Signature/replay/schema failure is rejected and logged; timeouts use bounded retry; uncertain transaction results require source-domain reconciliation, not duplicate submission.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| outbound intent | persist envelope | queued | Integration operators configure connector metadata; outbox. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| inbound | authenticate/validate | accepted/rejected | Integration operators configure connector metadata; signature/schema/replay. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| accepted | dedupe/enqueue | queued | Integration operators configure connector metadata; business key. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| queued | dispatch | ack/rejected | Integration operators configure connector metadata; domain owner decides. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| transport fail | safe retry | retry/dead-letter | Domain owner; bounded. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| ambiguous | reconcile | resolved/pending | Independent replay approver; no presumed result. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| dead-letter | approved replay | queued | Independent replay approver; dedupe. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| unsent | cancel | cancelled | Integration operators configure connector metadata; producer approval. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
integration_connections, messages, webhooks, attempts, incidents. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| GET | `/api/v1/integration/connectors` | view connector; validate required fields, source IDs, org scope, state and version. | `integration.connector.view`; Integration operator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| PATCH | `/api/v1/integration/connectors/{id}/manage` | manage connector; validate required fields, source IDs, org scope, state and version. | `integration.connector.manage`; Security credential steward; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/integration/messages` | view message; validate required fields, source IDs, org scope, state and version. | `integration.message.view`; Support investigator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/integration/messages/{id}/retry` | retry message; validate required fields, source IDs, org scope, state and version. | `integration.message.retry`; Domain owner; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/integration/messages/{id}/replay` | replay message; validate required fields, source IDs, org scope, state and version. | `integration.message.replay`; Independent replay approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/integration/messages/{id}/reconcile` | reconcile message; validate required fields, source IDs, org scope, state and version. | `integration.message.reconcile`; Independent replay approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/integration/credentials/{id}/rotate` | rotate credential; validate required fields, source IDs, org scope, state and version. | `integration.credential.rotate`; Independent replay approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/integration/messages/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `integration.message.view`; Integration operator | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/integration/messages/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `integration.message.view`; Integration operator | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: view, manage, retry, replay, reconcile, rotate. Adapter health, queued/sent/failed/dead-letter messages, retry counts, reconciliation status and credential expiry metadata (never secret values). Signature/replay/schema failure is rejected and logged; timeouts use bounded retry; uncertain transaction results require source-domain reconciliation, not duplicate submission. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Transports domain contracts, never owns domain transactions. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence. Preserve working evidence, document versions, message attempts or security telemetry as append-only according to this domain; keep Module 21 audit findings distinct from Module 26 technical incidents.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific duplicate message deduplication, dead-letter and replay scenarios.
## 15. Acceptance criteria
Inbound and outbound messages authenticate, validate and deduplicate; retries/replays are safe and financial ambiguity is reconciled by the domain owner. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Signature/replay/schema failure is rejected and logged; timeouts use bounded retry; uncertain transaction results require source-domain reconciliation, not duplicate submission.

## Reporting and operational visibility
Adapter health, queued/sent/failed/dead-letter messages, retry counts, reconciliation status and credential expiry metadata (never secret values).

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
