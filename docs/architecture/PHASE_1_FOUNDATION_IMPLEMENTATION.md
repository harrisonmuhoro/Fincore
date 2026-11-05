# Phase 1 Foundation Implementation Record

**Scope:** Modules 01–05, implemented within the existing Laravel/React modular monolith. This record describes the code currently present; it does not mark transaction modules as delivered.

## Delivered capabilities

| Module | Implemented scope | Owned records |
|---|---|---|
| 01 System foundation | Typed setting versions with compare-and-swap updates and hash audit entries; transaction-safe reference sequences; shared outbox event envelope. | `system_settings`, `reference_sequences`, `system_configuration_audits`, `domain_outbox_events` |
| 02 Identity and access | Email invitations and activation, password policy, Sanctum token login/logout, TOTP MFA enrollment, failed-login lockout, RBAC permission checks, effective scoped role assignments, suspension and token revocation. | `users`, `iam_roles`, `iam_permissions`, role pivots, `iam_access_audits` |
| 03 Organization | Draft organization units and effective-dated parent/child links, hierarchy cycle prevention, independently approved activation and history-preserving status changes. | `org_units`, `org_relationships`, `cost_centres`, `programmes`, `project_refs` |
| 04 Workflow | Versioned permission-based workflow definitions, immutable decisions, pinned instances, independent approval, return/reject, time-bounded delegation, requester cancellation and escalation state. | `workflow_definitions`, `workflow_steps`, `workflow_instances`, `workflow_decisions`, `workflow_delegations` |
| 05 Financial master data | Versioned account/fund/classification/dimension/rule records, account combinations, currency metadata, financial calendars/years/periods and controlled period transitions. | `finance_*`, `financial_calendars`, `financial_years`, `accounting_periods`, `period_transition_history` |

All new schema is created through additive Laravel migrations. Cross-module approval IDs are references rather than foreign keys. `domain_outbox_events` records versioned event envelopes transactionally; event delivery workers and consumer projections are deferred to the shared integration phases.

## API surface

All endpoints are under `/api/v1` and use the Phase 0 response envelope and request ID. Important routes include:

- Identity: `POST /auth/login`, `/auth/activate`, `/auth/mfa/setup`, `/auth/mfa/confirm`, `/auth/logout`; user invitation/listing, suspension, reinstatement, role creation and assignment under `/users` and `/roles`.
- System: `GET /system/settings`, `PATCH /system/settings/{key}`.
- Organization: `/organization/units` and `/organization/relationships` draft, submit, list and activation operations.
- Workflow: `/workflow-definitions`, `/workflows`, `/workflow-delegations`, workflow decisions, cancellation and escalation.
- Financial master data: `/finance/master-data/versions`, account/reference/combination routes, `/finance/calendars`, `/finance/financial-years`, `/finance/accounting-periods`, and `/finance/currencies`.

Application requests cannot directly edit lifecycle statuses. Workflow definitions are pinned to instances. Role assignments and protected financial/organization activations require an approved workflow and a different actor from the requester. Financial posting integrations must call `FinancialPeriodGuard::assertOpen()`; periods outside `open` are rejected.

## Bootstrap and operations

After configuring the backend environment and database:

```powershell
php artisan migrate --seed
php artisan iam:bootstrap-operator operator@example.test
```

The bootstrap command prompts for a name and initial password. It grants a narrowly scoped foundation operator role for platform setup and access administration; it does not grant finance approval, posting, period reopen, or other business authority. The first sign-in must enroll TOTP MFA. Configure `FRONTEND_URL` and a real mail transport before inviting staff. Do not use the local log mailer for operational accounts.

Seeding registers the Module 01–05 permission vocabulary, baseline one-step workflow definitions and the KES currency metadata as the documented demonstration default. It does not create a user, county hierarchy, chart of accounts, fiscal calendar, taxonomies, or approval limits.

## Open limitations and decisions

- The outbox is durable but no polling publisher, retry worker, or consumer idempotency store has been delivered. Consumers must not assume events have reached another module yet.
- Financial period close remains unavailable until Module 13 provides reconciliation readiness. This is a deliberate 409 response. Period reopening uses its own audited approval path; it cannot be performed through the open action.
- Generic settings currently take effect immediately; scheduled future effective dates and high-risk setting classifications need the platform owner's policy.
- Organization type dictionaries and exact hierarchy are configurable and intentionally unseeded. Scoped reads filter by assigned unit scope; the county/tenant boundary remains unresolved.
- The master-data API permits the owner to enter account types, funds, sources, classifications, dimensions, and accounting rules. No approved taxonomy, accounting basis, or official fiscal dates have been supplied.
- TOTP uses RFC 6238 SHA-1 six-digit time codes with a one-step clock tolerance. MFA recovery, trusted-device policy, password-reset policy, token lifetime, and account-retention policy need security-owner decisions.
- Approval roles and limits are not assigned by seed data. Administrators must configure roles and permissions using the county-approved segregation matrix before operational use.
- Procurement, budget, ledger, revenue, payment, external-system and government integrations remain out of scope.

## Verification record

The SQLite in-memory migration and seed cycle completed successfully. PHP syntax checking passed for 83 PHP files, and the TOTP implementation matched the RFC 6238 SHA-1 test vector. Route discovery registered 51 API routes. PHPUnit execution is blocked in this checkout because PHP reports the Composer bootstrap as unreadable; MySQL integration is blocked by sandbox loopback socket restrictions. See the delivery report for the exact check outcomes.
