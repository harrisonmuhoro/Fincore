# MODULE 06 — Budget Planning and Preparation

## A. Module overview
**Purpose:** Own budget calendars, departmental estimates/submissions, ceilings, recurrent and development budgets, programme-based budgeting, consolidation, review, approval, and approved versions. This specification is conceptual. Business owners, exact policies and schemas require validation. Primary users include relevant departmental operators, authorized approvers, administrators and auditors. Value: controlled, traceable execution with clear ownership.

## B. Functional scope
Own: Own budget calendars, departmental estimates/submissions, ceilings, recurrent and development budgets, programme-based budgeting, consolidation, review, approval, and approved versions. User operations cover create, view, update draft, submit and permitted lifecycle actions. Administration configures reference data and access within delegated authority. Reporting is limited to this domain's records and status. Out of scope: writing another module's authoritative records.

## C. Business workflows
Start with an authorized user and valid reference data. Create a draft with a generated reference and required fields; validate scope, uniqueness, effective dates and related IDs. Save as draft, submit for configured workflow approval, and record each decision with actor/time/reason. Approvers may approve, return for correction or reject; returned items retain history and can be resubmitted. On approval perform the module-owned final action atomically, publish the resulting domain event after commit and expose terminal status. Failed validations leave no partial financial effect. Define cancellation, amendment and reversal rules before implementation; posted financial events are never silently edited/deleted.

## D. Database ownership
Candidate owned table groups: budget_calendars, versions, estimates, ceilings, submissions. Use stable primary keys, timestamps and explicit statuses; document FKs, indexes for organization/status/date and unique business/reference/idempotency keys. Foreign references to other domains do not confer ownership. Monetary columns use DECIMAL. Preserve audit/posted history; no cascading deletion of financial records. Final entities, key type, constraints and retention need schema review.

## E. Backend architecture
Laravel route/controller adapters, Form Requests, owned Eloquent models, domain service/actions, Policies/Gates, API Resources and domain events. Add listeners/jobs/scheduled tasks only for real asynchronous or timed behavior. Keep controllers thin, transaction boundaries explicit, and cross-module calls behind contracts. Do not invent filenames or namespaces before inspecting repository conventions.

## F. Frontend architecture
Provide list/search/filter and detail views; create/edit draft form; lifecycle/approval queue where applicable; history/evidence panel and domain dashboard summaries. Use established shell and component library, strict TypeScript, accessible validation/loading/empty/error states and working actions. Suggested route family: /[module], /[module]/:id, /[module]/new; adapt to app routing conventions.

## G. API specification
Conceptual versioned family: GET /[resources], POST /[resources], GET /[resources]/{id}, PATCH /[resources]/{id}; explicit POST command routes for submit/approve/return/reject and module-specific finalization. Paginate/filter allowlisted fields. Authenticate with Sanctum or approved service identity; authorize every object and organizational scope. Form and domain validation; decimal-safe monetary serialization; standard errors; idempotency for sensitive commands. Publish OpenAPI contract after decisions.

## H. Permissions and security
Define module.view/create/update_draft/submit/approve/reject/administer/export permissions and least-privilege scope. Separate maker and approver; forbid self-approval where relevant. Enforce organization and record scope server-side. Mask/restrict sensitive data; protect uploads/downloads; log privileged changes through canonical audit service. Approval limits remain configurable and await validated authority.

## I. Integration dependencies
Depends on: Depends on organization, financial master data, workflow, documents and IAM contracts. Budget execution consumes approved budget versions. Consuming modules must be confirmed in the cross-module map. Consume only published contracts/events; publish a versioned event after committed state change with event ID, schema version, entity ID, timestamp, actor/scope references, correlation/causation IDs and minimal payload. Common events include lifecycle approved/completed; choose exact names and fields with consumers. External systems remain mock adapters absent specifications/credentials/authorization.

## J. Testing and acceptance criteria
Unit-test rules and state transitions; feature-test endpoints, validation and responses; integration-test dependencies/events; security-test scope, authorization, sensitive data and segregation; test financial invariants/concurrency/idempotency when applicable. Complete when the scoped workflow works end-to-end, ownership and access controls hold, documented contracts are honored, UI actions work, migration/API docs match behavior and relevant regression checks pass.

## Decisions and constraints
Do not hardcode legal or financial thresholds. Use fictional seed data; do not claim official government affiliation or integrations. Use MySQL 8 and project-approved migration conventions. Preserve existing repository changes and document unresolved decisions rather than fabricating policy.


## Detailed module specification

### Purpose and boundary
Budget Planning Own only the records listed here: Version snapshots immutable after approval; totals reconcile.. Cross-domain data is referenced by owner ID.

### Functional requirements and actors
Operators manage drafts and lifecycle actions; designated reviewers approve, return or reject; administrators configure domain reference data; auditors receive scoped read-only access. Functions include: Budget Planning

### Business rules and state
Calendars, ceilings, departmental estimates, consolidation, review, approved versions. Lifecycle: draft → submitted → under_review → approved/returned/rejected → completed. Return requires a reason and preserves history. Finalization is atomic. Posted accounting effects require reversal, never silent edit.

### Data relationships
Candidate owned tables: Version snapshots immutable after approval; totals reconcile.. Index scope/status/business date; enforce unique natural/reference and idempotency keys. Reference org/user/financial IDs through their owning modules. Use DECIMAL for every amount and no cascading deletion of financial evidence.

### Backend and frontend
Backend: Laravel route/controller, Form Requests, domain models/services/actions, Policies/Gates, Resources, events and queue jobs where needed. Frontend: React/TypeScript list, detail, create/edit, filter and lifecycle queue at /budget-planning-and-preparation; accessible error/loading/empty states and functioning actions.

### API contract and permissions
budget_calendars, versions, ceilings, estimates, submissions, lines. All endpoints use /api/v1, authenticate, authorize record and organization scope, validate transitions, paginate/filter lists and return stable resource IDs. Explicit POST lifecycle commands; no generic status patch. Permissions are module.view/create/update/submit/approve/administer/export; maker and approver separated.

### Dependencies, audit and security
/api/v1/budgets/calendars, /submissions; POST approve. Use documented contracts and outbox events; envelope carries event ID/type/version, time, entity and correlation IDs with minimal payload. Consumers are idempotent. Protect sensitive fields/files; log privileged actions using canonical audit infrastructure; server-side authorization is mandatory.

### Test and acceptance conditions
Unit-test rules/state transitions; feature-test API validation/authorization; integration-test dependencies/events/retries; security-test scope and leakage; financial domains test decimals, locking, idempotency, duplicate prevention and reconciliation. Complete when workflows, ownership, permission boundaries, API/UI, audit, tests and docs meet these requirements.

### Unresolved decisions
Confirm county roles, retention, thresholds, statutory or accounting requirements and integration authorization with owners. Use fictional data; no official affiliation or live integration is implied.

