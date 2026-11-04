# Financial Control Specification

This document assigns cross-module controls. It does not establish county policy, statutory thresholds, accounting basis, or delegated financial limits. Those remain explicit stakeholder decisions in [Open Business Decisions](OPEN_BUSINESS_DECISIONS.md).

## Money and currency

- Persist monetary amounts using MySQL `DECIMAL` with precision and scale selected for approved transaction ranges. Use decimal-safe arithmetic in PHP; never convert money through binary floating point.
- Store currency with every monetary amount or unambiguously at its owning transaction. KES is the demonstration default. Multi-currency conversion, rate source, rounding and revaluation policy require approval before implementation.
- Define rounding at domain boundaries and retain the source amount, currency, applied rate/version and resulting amount where conversion is approved.

## Budget availability and commitments — Module 07

Module 06 owns approved budget versions; Module 07 owns allocations/releases, reservations, commitments, adjustments, liquidation and execution balances. Procurement (09), projects (17), grants (18) and AP (12) request or consume those controls through its contract. They must not maintain competing available-balance ledgers.

An availability decision must identify the budget version, fund and required dimensions; account for approved allocations, releases, reservations, commitments and actuals under a county-approved formula; and record the decision with the reservation/commitment in one transaction. Lock the balance or use an equivalent serializable/concurrency-safe mechanism. Reject stale versions and insufficient balance. Overrides require an authorized independent role, reason, evidence and audit reference; they cannot silently bypass a legal or closed-period constraint.

Commitment creation, adjustment, cancellation and liquidation use durable business keys and idempotency. A repeated identical request returns the recorded outcome; a conflicting payload using the same key returns a conflict. Reversal/cancellation releases only the applicable outstanding balance and preserves history.

## Journal and ledger integrity — Module 13

Every journal has at least the required debit and credit lines, uses valid active account combinations, identifies currency and period, and balances per currency before posting. It carries a stable source-document type/ID and correlation reference. Source modules retain their records; the ledger stores references and the authoritative posted journal.

Posting is atomic and requires an open period from Module 05. A posted journal and its lines are immutable: no update/delete path is allowed. Corrections are new, authorized, balanced journals linked to the original, with reason, actor and approval history. Duplicate source posting is blocked by a unique source/idempotency key. Failed validation or database work leaves no partial journal posting.

## Period ownership and locking — Module 05

Module 05 alone owns financial years, accounting periods, their status, calendar configuration and transition history. Module 13 supplies close-readiness/reconciliation evidence but does not change period status. All posting domains must check the authoritative period status at commit time, not only when a form is opened. Closing serializes against concurrent posting. Reopening requires a separate privileged request, independent approval, reason/evidence and immutable audit record; applicable county policy must be confirmed.

## Payables and treasury boundary — Modules 12 and 14

Module 12 owns invoice liability, credit notes and approved payment vouchers. Voucher approval is not payment execution. Module 14 owns bank accounts, release/execution outcomes, cashbook and bank reconciliation. A voucher handoff is idempotent and does not assert cash movement. Treasury execution must use an authorized batch, verify beneficiary/bank details and duplicates, and persist an external reference where one exists.

If a payment outcome is uncertain, mark it uncertain and hold automatic resend until evidence or statement reconciliation resolves it. Confirmed failure, confirmed success and unresolved outcome are distinct states. Reconciliation is an independently authorized decision and keeps source references.

## Revenue and assets

Module 15 owns assessment, issued receipts, allocations, arrears and refund lifecycle. Issued receipts are immutable; corrections use linked void/reversal with a reason. Receipt issuance is idempotent and protected from duplicate collection callbacks. Refund request, approval and execution are separate recorded steps.

Module 16 owns asset register and lifecycle. Acquisition/disposal accounting entries are requested from Module 13; asset status alone does not create a posted journal. Depreciation, capitalization, impairment and disposal methods require approved county accounting policy.

## Shared safeguards and evidence

All consequential financial commands require server-side RBAC, organization scope, maker-checker where configured, transaction boundaries, audit actor/time/reason, correlation/causation IDs and idempotency where retry can duplicate an effect. Cross-module events use an outbox written with the source transaction; consumers deduplicate and expose failed delivery for reconciliation. A consumer failure must not silently mutate or roll back a separately committed source record.

## Minimum invariant tests

- Concurrent reservations/commitments cannot overspend a budget balance.
- Unbalanced, duplicate, inactive-combination or closed-period journals cannot post; failed work leaves no partial ledger effect.
- Posted journal edits/deletes fail; authorized reversal is linked and balanced.
- Period close and posting serialize so a posting cannot commit into a period after close succeeds.
- Voucher approval cannot execute payment; duplicate payment request cannot create a second execution.
- Uncertain payment is not classified as failure or resent without reconciliation evidence.
- Duplicate receipt/import/event keys do not create duplicate financial effects.
- Unauthorized approval, self-approval and out-of-scope financial access are denied and audited.
