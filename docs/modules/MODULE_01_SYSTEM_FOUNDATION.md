# MODULE 01 — System Foundation and Configuration

## 1. Purpose and scope
Provide shared runtime configuration, API versioning, reference-number generation, generic settings, logging/error conventions, health checks and platform utilities. It does not own financial periods or domain transactions; module 05 owns the canonical fiscal calendar.

## 2. Functional requirements
Typed/effective-dated system settings, atomic sequence generation, API version support, environment configuration validation, health/readiness checks, structured error responses, correlation IDs, shared logging and operational parameter management.

## 3. Actors and responsibilities
Platform administrators configure approved parameters; DevOps monitors health/deployment; module teams consume contracts. No business approver role is defined here.

## 4. Permissions and access restrictions
system.settings.view/update, system.sequences.admin, system.health.view. Protect secrets through deployment secret management; never expose environment values through APIs or health endpoints.

## 5. Business rules
Settings have explicit type, scope, effective dates and audit trail. Sequence allocation is concurrency-safe and never reuses issued references. Feature/config changes do not mutate posted transactions.

## 6. Workflows and state transitions
Configuration change: draft → reviewed where required → effective → superseded. Validate type/scope/effective interval and record actor/reason. Reference request allocates atomically and returns one durable value. Health probes disclose only operational state, not credentials.

## 7. Database ownership and entity relationships
Candidate tables: system_settings(id, key, value_json, scope, effective_from, effective_to, status, created_by), reference_sequences(id, namespace, period_key, next_value, version), config_change_log(id, setting_id, before_hash, after_hash, actor_id, changed_at). Unique key/scope/effective interval and sequence namespace/period. Financial-year and period tables belong to module 05.

## 8. Backend architecture
Laravel config providers, health controller, Settings/Sequence models, SettingsService and ReferenceNumberService, Form Requests, Policies, API Resources, config validation command and health checks. Sequence service uses transaction/row lock. Avoid a generic utility dumping ground.

## 9. API contracts and endpoint responsibilities
GET /api/v1/system/settings (system.settings.view; scope filter); PATCH /api/v1/system/settings/{key} (system.settings.update; typed value/effective date validation); GET /api/v1/health (public minimal liveness); GET /api/v1/ready (operator only, dependency status). Reference generation is an internal contract, not a public unauthenticated endpoint. Return redacted settings.

## 10. Frontend responsibilities
System settings page, configuration history, health panel and version/status display. Admin form validates types and effective dates; secrets are never displayed. State management uses existing query/cache conventions.

## 11. Cross-module dependencies and events
All modules depend on configuration, reference and observability contracts. Depends on IAM (02) for admin authorization. Emits ConfigurationChanged and ReferenceIssued only when consumers need them; does not publish domain finance events.

## 12. Security and audit requirements
Least-privilege platform administration, independent review for high-risk configuration, secret redaction, correlation IDs and append-only configuration change audit. Health endpoints avoid internal topology disclosure.

## 13. Testing requirements
Unit-test setting validation and sequence formatting; concurrency test no duplicate sequence; feature-test access/redaction; integration-test configuration boot and health dependencies; test effective-date overlap and audit history.

## 14. Acceptance criteria
No secret exposure; unique concurrent reference generation; settings changes are authorized and traceable; health/readiness accurately distinguish app availability and dependencies; all modules can consume stable contracts.

## 15. Unresolved business decisions
Reference number formats, scopes, reset cadence, settings approval tiers, health disclosure and operational retention require platform-owner confirmation.

## 16. Implementation notes
Modular monolith, Laravel/PHP 8.3+, MySQL 8, Redis where needed. Avoid microservices and avoid placing module 05 financial-period ownership here.
