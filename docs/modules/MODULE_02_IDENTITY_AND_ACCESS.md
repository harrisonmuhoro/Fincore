# MODULE 02 — Identity and Access Management

## 1. Purpose and scope
Authentication, accounts, MFA, RBAC, organization scope. This module owns users, roles, permissions, assignments, sessions. It does not own records of its dependencies or consumers.

## 2. Functional requirements
Implement the owned capabilities in scope: Authentication, accounts, MFA, RBAC, organization scope.. Support this workflow: Invite/create → verify identity → activate credentials and MFA → assign scoped roles → active; suspension revokes tokens/sessions, reinstate requires authorized action.. Reject or route the documented exceptions: Expired invitation, failed MFA, lockout and suspicious login trigger denial and security alert; deactivation revokes all tokens and sessions; recovery requires verified workflow.. Expose the operational views: Active/suspended users, MFA enrollment, failed-login trends, privileged assignments and periodic access review.. Cross-module references are read/commanded through their owner contracts; this module does not duplicate their records.
## 3. Actors and responsibilities
Staff members are invited by HR/authorized administrators; IAM stewards verify identity; security admins manage privileged accounts; auditors review access-change trails. Departmental access comes from module 03 assignments.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `iam.user.invite` | Identity administrator | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `iam.user.activate` | IAM steward | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `iam.user.suspend` | Privileged access approver | Independent approval or separate authority where the action changes a controlled state | Independent authority; no maker self-approval; record reason/evidence and delegation scope. |
| `iam.user.reinstate` | Access administrator | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `iam.session.revoke` | Security administrator | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `iam.role.assign` | Security administrator | Independent approval or separate authority where the action changes a controlled state | Validate required fields and owner references; draft-only edits and scoped access. |
| `iam.permission.manage` | Security administrator | No | Privileged scope; changes versioned, reasoned and independently reviewed where high risk. |
| `iam.access_review.complete` | Security administrator | No | Validate required fields and owner references; draft-only edits and scoped access. |
| `iam.user.view` | IAM steward | No | Apply organization, record-sensitivity and linked-source scope. |
| `iam.user.update_draft` | IAM steward | No | Draft-only modification with expected version; preserve change history. |
Approval limits/thresholds require county authorization. Delegations are time- and scope-bounded, revocable and audited. Break-glass access is time-limited, reasoned, alerted and independently reviewed. Platform administrator access confers no domain approval authority.
## 5. Module-specific business rules
Suspension blocks auth; privileged changes audited.

## 6. Detailed business workflows
Invite/create → verify identity → activate credentials and MFA → assign scoped roles → active; suspension revokes tokens/sessions, reinstate requires authorized action.
Initiation, mandatory validation and approval gates are defined above. On failure, Expired invitation, failed MFA, lockout and suspicious login trigger denial and security alert; deactivation revokes all tokens and sessions; recovery requires verified workflow.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor and preconditions | Side effects, audit and failure recovery |
|---|---|---|---|---|
| invited | verify identity | verified | Staff members are invited by HR/authorized administrators; invitation valid and evidence reviewed. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| verified | activate with MFA | active | Staff members are invited by HR/authorized administrators; credentials/MFA set. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| active | suspend | revoked and suspended | Privileged access approver; reason and all sessions revoked. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| locked/suspended | verified reinstatement | active | Staff members are invited by HR/authorized administrators; independent review. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |
| active | terminate | terminated | Staff members are invited by HR/authorized administrators; authorized request, preserve history. | Record state/version, actor, time, reason and approval atomically; emit event after commit. On failed validation or downstream owner response, retain prior state and create a visible retry/reconciliation case. Recover only by explicit domain-specific correction, safe recovery or owner escalation; preserve history. |

No caller may patch lifecycle status directly. Module 04 records approval decisions; this module applies them to its own records.
## 8. Database ownership and entity relationships
users, roles, permissions, assignments, sessions. Use stable primary keys, timestamps, actor/status/effective dates as appropriate, scoped/date indexes, and unique business/idempotency constraints. Foreign keys remain within this domain; cross-domain references use stable owner IDs/contracts. retain domain audit evidence and avoid destructive cascading deletes.
## 9. Backend responsibilities
The module owns the records and relationships listed in section 8. Laravel application services implement the workflows in section 6; Form Requests validate shape; Policies/Gates enforce named capabilities, organization scope and maker-checker; Resources define stable responses. Use database transactions for state changes, append audit decisions, and publish versioned outbox events after commit. Cross-module dependencies are called through the documented owner contract; no direct writes to other module records.
## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / responsible role | Response, audit and errors |
|---|---|---|---|---|
| POST | `/api/v1/users` | invite user; validate required fields, source IDs, org scope, state and version. | `iam.user.invite`; Identity administrator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/users/{id}/activate` | activate user; validate required fields, source IDs, org scope, state and version. | `iam.user.activate`; IAM steward; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/users/{id}/suspend` | suspend user; validate required fields, source IDs, org scope, state and version. | `iam.user.suspend`; Privileged access approver; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/users/{id}/reinstate` | reinstate user; validate required fields, source IDs, org scope, state and version. | `iam.user.reinstate`; Access administrator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/users/sessions/{id}/revoke` | revoke session; validate required fields, source IDs, org scope, state and version. | `iam.session.revoke`; Security administrator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/roles/{id}/assign` | assign role; validate required fields, source IDs, org scope, state and version. | `iam.role.assign`; Security administrator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| PATCH | `/api/v1/permissions/{id}/manage` | manage permission; validate required fields, source IDs, org scope, state and version. | `iam.permission.manage`; Security administrator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| POST | `/api/v1/access-review/{id}/complete` | complete access review; validate required fields, source IDs, org scope, state and version. | `iam.access_review.complete`; Security administrator; independent approval for controlled transitions. | 200/201/202 with ID/state/version/correlation ID; 401/403/404/409/422/429 as applicable. Audit actor/decision; use idempotency key for duplicate-sensitive requests. |
| GET | `/api/v1/users/{id}` | Read record state, version, decisions and links; validate organization scope and record visibility. | `iam.user.view`; IAM steward | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| PATCH | `/api/v1/users/{id}` | Update mutable draft fields with expected version; validate organization scope and record visibility. | `iam.user.view`; IAM steward | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable. Read access is audited where sensitive; edits record before/after change. |
| GET | `/api/v1/users` | List records with allowlisted filters, sorting and bounded pagination; validate organization scope and record visibility. | `iam.user.view`; IAM steward | 200 with stable ID/state/version; 401/403/404/409/422/429 as applicable; audit sensitive reads and draft changes. |
Reads are paginated with allowlisted filters/sorts. Commands reject unknown fields and caller-supplied approver identity. Protected fields are redacted.
## 11. Frontend responsibilities
Provide operational registers and workspaces for: invite, activate, suspend, reinstate, revoke, assign, manage, complete. Active/suspended users, MFA enrollment, failed-login trends, privileged assignments and periodic access review. Expired invitation, failed MFA, lockout and suspicious login trigger denial and security alert; deactivation revokes all tokens and sessions; recovery requires verified workflow. Detail views show owner IDs, state/version, decision and evidence history. Lists support scoped search, filters and bounded pagination. Forms surface server validation and stale-state conflicts; role-based control visibility is only a usability aid, with access rechecked in Laravel.
## 12. Cross-module dependencies and integration events
Consumes org IDs; all modules consume authorization. Only this module writes its records. Domain events publish after commit through outbox with event_id, type/version, occurred_at UTC, producer, entity ID, correlation/causation IDs and minimal payload. Consumers dedupe and retry safely. External adapters remain mocked until specifications, credentials and authorization are approved.

## 13. Security and audit requirements
Enforce authentication and named permissions through Laravel Policies/Gates on every read and command. Apply organization/record scope server-side, least privilege, masking and private storage where applicable. Append actor, timestamp, decision, reason and correlation ID to the canonical audit trail; domain administrators cannot alter audit evidence.
## 14. Testing requirements
Test every documented state transition and its failure conditions, approval separation, unauthorized/out-of-scope access, duplicate/idempotent request, stale update, event retry, audit evidence and owner-contract integration. Test domain-specific business validation and reporting scenarios.
## 15. Acceptance criteria
Invitations require verification and MFA; suspension or termination blocks authentication and revokes existing sessions; privileged role changes are independently approved and audited. Acceptance is demonstrated by API, authorization, lifecycle, audit and failure-recovery tests that pass for the stated scope.
## 16. Unresolved business decisions
Confirm roles, approval limits, legal/accounting rules, retention, classification and integration authority with accountable owners. No government affiliation or official integration is claimed.

## Exceptions and failure handling
Expired invitation, failed MFA, lockout and suspicious login trigger denial and security alert; deactivation revokes all tokens and sessions; recovery requires verified workflow.

## Reporting and operational visibility
Active/suspended users, MFA enrollment, failed-login trends, privileged assignments and periodic access review.

## Implementation notes
This is a modular-monolith domain using Laravel/PHP 8.3+, React/TypeScript/Vite/Tailwind/shadcn, MySQL 8 and Redis where appropriate. Use fictional data; do not add microservices without demonstrated need.

Account invitation uses the configured mail transport and the password-reset token store for one-time activation. New users set a password, verify the invitation, then enroll RFC 6238 TOTP before their account becomes active. A deployment-only `iam:bootstrap-operator` command creates a foundation operator without business approval permissions; there is no default seeded password or county finance approver.
