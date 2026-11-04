# Testing Strategy

## Test levels
- Unit: domain calculations, invariants, state transitions, rounding and permission decisions.
- Feature/API: methods, request validation, response shapes, auth, Policies/Gates, organization scopes, status codes, pagination and errors.
- Database: MySQL 8 migrations, constraints, transaction rollback, row locking, unique/idempotency behavior and period locks.
- Workflow: sequential/parallel routing, delegation expiry, no self-approval, return/rejection, escalation and workflow-version behavior.
- Integration: module contract tests, outbox atomicity, duplicate event delivery, retry/dead-letter, webhook signature/replay, mock adapter reconciliation.
- Frontend: component/form validation, accessible states, action visibility plus backend-denial behavior, critical journey E2E.
- Security: IDOR, privilege escalation, MFA/session revocation, upload malware/type/size, secret/PII leakage, audit integrity.
- Performance/recovery: large scoped lists, report/export load, queue backlog behavior, backup restore and documented recovery tests.

## Financial invariant scenarios
1. Concurrent commitments against a balance: serialized/locked checks cannot overspend; one request fails with explainable conflict.
2. Duplicate payment request/provider callback: idempotency key and business key prevent a second execution/receipt.
3. Journal with unequal debits/credits, closed period, inactive account combination or duplicated source: posting rejected and no partial lines committed.
4. Posted journal: update/delete denied; linked authorized reversal balances and preserves original.
5. Voucher approval alone does not imply execution; unknown bank outcome remains pending reconciliation and cannot trigger blind resend.
6. Failed downstream event processing: outbox retains delivery; retry/dedupe completes without duplicate source transaction.
7. Period reopening: closed period remains locked absent separate privileged approved action and audit record.

## Domain examples
IAM tests suspension revokes sessions; procurement tests conflict-declared evaluator cannot participate; revenue tests unique receipt/refund approval; inventory tests stock cannot become negative through concurrent issues; payroll tests duplicate batch hash and control-total mismatch; audit tests evidence cannot be altered by auditee.

## CI quality gates
Run relevant unit/feature/integration/security suites, static analysis, formatting and frontend build. Release gates include full regression, API compatibility, migration review, vulnerability checks and backup/restore readiness. Record exact command/environment/outcome; do not report unrun tests as passing. Use fictional fixtures and no production personal/financial data.
