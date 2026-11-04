# RBAC and Approval Matrix

| Capability | Maker | Independent approver | Administrator | Auditor |
|---|---|---|---|---|
| Master data | Draft/request | Approve/effect-date | Configure, not self-approve | Review history |
| Budget/procurement/payment | Create and submit in scope | Approve within configured limits | Configure workflow, no default bypass | Read evidence |
| Journals/period close | Prepare | Separate post/close authority | No bypass | Read immutable trail |
| Supplier bank data | Restricted request | Independent verification | Privileged and logged | Masked access |
| Audit findings | Record finding | Auditee responds; independent close | Cannot alter evidence | Authorized full trail |

Enforce least privilege, org scope, maker-checker, no self-approval, dated/scope-limited delegation, conflict checks and privileged access logs. MFA required for privileged users as policy. County must validate roles, limits, emergency access and approval tiers; do not hardcode legal thresholds.
