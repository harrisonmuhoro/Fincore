# Phase 2 Shared Controls Implementation Record

**Scope:** Modules 23 Document and Record Management, 24 Notification and Communication, and 26 System Audit, Security and Monitoring. This phase does not implement Modules 06, 07 or 13 and does not enable financial transactions.

## Repository baseline and compatibility

The application remains a Laravel/PHP 8.3 modular monolith with versioned REST endpoints, Sanctum, MySQL-compatible migrations, private local file storage, Laravel queues, React/TypeScript, and the existing UI shell. The Phase 0 response envelope, request ID middleware, Phase 1 permissions, and outbox remain in place. No new runtime package or microservice was added. The pre-existing untracked root `dev.bat` is unrelated and preserved.

## Delivered records and boundaries

| Module | Owned records | Implemented behavior |
|---|---|---|
| 23 Documents | `records_documents`, `records_document_versions`, `records_document_links`, `records_document_access_events` | Upload with MIME/size checks and SHA-256, owner-scoped reads, classification metadata, stable-ID links, access history, legal hold, private storage, quarantined status and clean-scan gated downloads. |
| 24 Notifications | `user_notifications`, `notification_preferences`, `notification_deliveries`, `notification_event_receipts`, `notification_announcements` | Recipient-only inbox/read state, versioned preferences, idempotent event delivery service, queued email based on preferences, independent announcement author/publisher and queued fan-out, plus a consumer for workflow completion events. |
| 26 Security | `security_events`, `security_incidents`, `security_incident_history`, `module_audit_events`, `module_audit_chain_heads`, `security_monitoring_snapshots` | Minimized authentication and IAM privileged-action telemetry, paginated event/incident views, incident state transitions, per-module hash-chained audit events and verifier, and authenticated operational check snapshots. IAM retains ownership of `iam_access_audits`; monitoring telemetry is a copy. |

Cross-module links store owner module and stable record ID without foreign keys or direct writes to producer data. Notification event consumption resolves the workflow requester through the workflow contract's owned record. Documents are owner-scoped until a shared linked-record authorization interface exists.

## APIs and UI

Authenticated, MFA-enforced APIs are under `/api/v1`: `/documents`, `/documents/{id}`, `/documents/{id}/versions`, `/documents/{id}/scan-result`, `/documents/{id}/hold`, `/documents/{id}/download`; `/notifications`, `/notifications/{id}/read`, `/notification-preferences`, `/announcements`, and `/announcements/{id}/publish`; `/security/events`, `/security/incidents`, `/security/incidents/{id}/transition`, and `/security/monitoring-snapshots`. Middleware enforces seeded `records.*`, `notification.*`, and `security.*` permissions; document owners are checked again inside the controller. Incident changes require the current version, transition-specific permission and valid state transition.

The React `Shared controls` page provides document upload/status, inbox/read, announcement draft/publication, security event listing, and incident creation/listing. API permissions remain authoritative; role based UI visibility is not a security boundary.

## Integration and security behavior

`NotificationService::deliver` deduplicates in-app messages by event/recipient and email attempts by event/recipient/channel. `notifications:consume-domain-events` currently supports `ApprovalCompleted`, notifies the workflow requester, records a consumer receipt, and leaves the shared outbox publication marker untouched. Announcement publication dispatches queued recipient fan-out after commit. Mail requires a configured queue and mail transport.

Files are stored on the non-public `private` disk, named with a generated UUID, and never served by a public URL. Download is denied unless the current version has a `clean` scan status. The scan-result endpoint records a named scanner and evidence but no antivirus engine or provider was supplied. Authentication failures and IAM privileged actions create minimized `security_events`; detailed secrets, passwords, MFA codes and raw authentication payloads are not copied into telemetry.

## Decisions and deferred work

- Malware scanning requires an approved scanner/provider and operational credential or in-process adapter. Until then, uploads remain quarantined and undownloadable unless an authorized operator records a scan result.
- No records retention schedule, classification taxonomy, legal hold authority matrix, disposition rule or destruction approval policy was supplied. Deletion/disposition is therefore not exposed; the hold flag cannot be used to destroy files.
- Document binary versions are immutable, stored separately, and every new version returns to quarantine pending scan.
- Cross-module linked-record authorization is not available; users can access their own documents, while `records.document.view_all` is a separately granted capability.
- General outbox publication, consumers beyond workflow completion, external email delivery receipts, failure/retry dashboards, automatic detection thresholds, automated backup freshness probes, organization-scoped security incident policy, and integration alerts remain deferred. Queue failures use Laravel's queue retry/failed-job behavior; delivery state dashboard is not provided.
- Notification event consumption is invoked with `php artisan notifications:consume-domain-events`; no scheduler frequency is assumed or enabled. Configure an operational schedule after owner approval.
- Security incident transitions currently use permissioned commands plus append-only history, not the generic approval engine; incident approval/closure authority needs confirmation.

## Operational setup

Run `php artisan migrate --seed` to add module tables and permissions. Configure the `private` local disk, queue worker, and approved mail transport before using queued notification delivery. Run `php artisan queue:work` for email and announcement jobs. Invoke the notification outbox consumer on an approved schedule. Never publish the private disk using `storage:link`.

## Verification record

Verified: SQLite in-memory `artisan migrate:fresh --seed --force` passed; route discovery registered 84 API routes; `php vendor/bin/phpunit --no-configuration tests/Feature` reported 26 tests, 24 passed and 2 environment-dependent skips; the frontend suite reported 9 passing tests; TypeScript typecheck, Oxlint, Vite production build, `vendor/bin/pint --test`, and `git diff --check` passed. MySQL-specific locking and production private-storage permissions require deployment-environment verification.
