# MODULE 02 — Identity and Access Management

## 1. Purpose and boundaries
Own user identity, authentication, password/MFA lifecycle, roles, permissions, organization scopes, assignments, sessions, suspension and access history. Do not own county organizational hierarchy (03) or workflow decisions (04).

## 2. Functional requirements
Provision and deactivate accounts; authenticate/logout; password reset; MFA enrollment/challenge; assign roles and scoped grants; suspend/reinstate users; inspect sessions and access history; provide admin and security review views.

## 3. Actors and permissions
Staff users, IAM administrators, security officers and auditors. Permissions include iam.users.view/create/update/suspend, iam.roles.manage, iam.scopes.assign, iam.sessions.revoke, iam.audit.view. Scope assignments require separate approval; privileged accounts require MFA per policy.

## 4. Business rules
Suspended accounts cannot authenticate or hold active tokens. Role grants are least privilege and effective-dated. Deny by default. Prevent privilege escalation through self-assignment. Record actor, reason and timestamp for role/scope changes. Authentication errors must not reveal whether an account exists.

## 5. Workflows and state transitions
Invited → pending_activation → active → suspended/disabled. Activation verifies identity and sets credentials/MFA as policy requires. Role assignment request → independent approval where privileged → effective grant. Revocation invalidates sessions/tokens. Password reset uses expiring single-use challenge.

## 6. Database ownership and relationships
Own users, roles, permissions, role_permissions, user_role_assignments, user_org_scopes, sessions/authentication events and MFA factors. Keys reference organization IDs from module 03; no duplicate organization tables. Index normalized login identifier, status and user/scope; enforce unique login and assignment tuples. Never store plaintext credentials.

## 7. Backend responsibilities
Laravel Sanctum authentication, controllers, Form Requests, User/Role/Scope models, credential and authorization services, Policies/Gates, API Resources, security events and queued notification jobs. Centralize scope evaluation; revoke tokens transactionally on suspension.

## 8. Frontend responsibilities
Login/MFA/reset pages; user directory/detail; role and scope assignment screens; session revocation; access history. Hide unauthorized actions for usability, show server errors safely, require confirmation for suspension/revocation.

## 9. API contracts
POST /api/v1/auth/login, /logout, /password/forgot, /password/reset, /mfa/challenge; GET/POST /api/v1/users; GET/PATCH /api/v1/users/{id}; POST /api/v1/users/{id}/suspend, /reinstate, /roles, /scopes; GET /api/v1/access-events. Enforce rate limits, CSRF/session protections as applicable, validation, MFA and object/scope authorization.

## 10. Cross-module dependencies
Depends on foundation (01) and organization references (03); all business modules consume its identity and authorization contracts. Emit UserActivated, UserSuspended, RoleAssignmentChanged and PrivilegedAccessRecorded with minimal payload; notification (24) and security monitoring (26) consume events.

## 11. Security and audit
MFA for privileged users; secure session cookies/token handling, rotation, expiry, brute-force protection and generic auth errors. Policies/Gates on every endpoint. Audit role/scope changes and privileged reads; protect personal data and restrict audit access.

## 12. Testing requirements
Test credential lifecycle, MFA, token revocation, suspended-user denial, role/scope boundaries, maker-checker, rate limiting, session expiry, IDOR and privilege escalation. Integration-test organization lookup and notification/security events.

## 13. Acceptance criteria
Users can authenticate and recover access securely; administrators can assign scoped roles without escalation; suspension revokes access; all protected endpoints enforce policy; privileged activity is traceable; tests prove denial as well as success.

## 14. Implementation notes
Confirm identity proofing, MFA methods, password/session policy, privileged role catalogue, privacy/retention and emergency access. Do not assume government identity-provider integration.
