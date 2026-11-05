# MODULE 23 — Document and Record Management

## 1. Purpose and scope
Secure upload, classification, versioning, linking, retention, downloads. This module owns documents, versions, classifications, links, retention_events. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Secure upload, classification, versioning, linking, retention, downloads.. Support this workflow: Upload privately → inspect type/size/hash/malware → classify/link/version → authorize read/download → retention hold/review → archive/dispose only under policy; evidence chain preserved.. Reject or route the documented exceptions: Malware, invalid MIME, oversize file or unauthorized link blocks upload/download; retention hold prevents disposition; failed scan quarantines file.. Expose the operational views: Storage/classification, version history, retention dates/holds, access/download history and quarantined file queue.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Records officers classify/retain; business users upload/link; evidence owners authorize access; authorized records managers dispose under approved retention; module policies control linked-record scope.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `records.document.upload` | Business uploader | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `records.document.classify` | Records officer | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `records.document.publish` | Evidence owner | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `records.document.hold` | Records manager | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `records.disposition.request` | Independent disposition approver | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `records.disposition.approve` | Linked-record user | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `records.document.download` | Linked-record user | No | Reapply row scope and masking; audit export/download. |
| `records.document.view` | Linked-record authorized user | No | Apply organization, record-sensitivity and linked-source scope. |
| `records.document.update_draft` | Linked-record authorized user | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Private storage; validate MIME/size/hash; authorize every download.

## 6. Detailed business workflows
Upload privately → inspect type/size/hash/malware → classify/link/version → authorize read/download → retention hold/review → archive/dispose only under policy; evidence chain preserved.
Initiation, mandatory validation and approval gates are defined above. On failure, Malware, invalid MIME, oversize file or unauthorized link blocks upload/download; retention hold prevents disposition; failed scan quarantines file.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| metadata | private upload | quarantine | Business uploader; hash/type scan. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| quarantine | scan/classify | available/rejected | Linked-record user; malware check. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| available | link/approve | linked | Linked-record user; source authorization. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| version current | upload new | old superseded/new current | Linked-record user; immutable history. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| document | legal hold | held | Records manager; disposal blocked. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| retention due | archive | archived | Linked-record user; approved schedule. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| eligible/no hold | approve disposition | disposed | Linked-record user; evidence. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| held | release hold | prior state | Records manager; authorized. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
documents, versions, classifications, links, retention_events. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/documents` | upload document; validate required fields, source IDs, org scope, state and version. | `records.document.upload`; Business uploader; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/documents/{id}/classify` | classify document; validate required fields, source IDs, org scope, state and version. | `records.document.classify`; Records officer; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/documents/{id}/publish` | publish document; validate required fields, source IDs, org scope, state and version. | `records.document.publish`; Evidence owner; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/documents/{id}/hold` | hold document; validate required fields, source IDs, org scope, state and version. | `records.document.hold`; Records manager; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/document-dispositions` | request disposition; validate required fields, source IDs, org scope, state and version. | `records.disposition.request`; Independent disposition approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/document-dispositions/{id}/approve` | approve disposition; validate required fields, source IDs, org scope, state and version. | `records.disposition.approve`; Linked-record user; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/documents/{id}/download` | download document; validate required fields, source IDs, org scope, state and version. | `records.document.download`; Linked-record user; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/documents/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `records.document.view`; Linked-record authorized user | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/documents/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `records.document.view`; Linked-record authorized user | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/documents` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `records.document.view`; Linked-record authorized user | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: upload, classify, publish, hold, request, approve, download. Storage/classification, version history, retention dates/holds, access/download history and quarantined file queue. Malware, invalid MIME, oversize file or unauthorized link blocks upload/download; retention hold prevents disposition; failed scan quarantines file. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumed by all relevant modules; IAM scopes. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence. Preserve working evidence, document versions, message attempts or security telemetry as append-only according to this domain; keep Module 21 audit findings distinct from Module 26 technical incidents.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific legal-hold blocking disposition and private access scenarios.
## 15. Acceptance criteria
Files remain private until scanned and authorized; access is checked against linked records; legal holds block disposition and destruction is evidenced. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Malware, invalid MIME, oversize file or unauthorized link blocks upload/download; retention hold prevents disposition; failed scan quarantines file.

## Reporting and operational visibility
Storage/classification, version history, retention dates/holds, access/download history and quarantined file queue.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.
