# Phase 3 — Budget and Ledger Implementation Record

## Scope delivered

Phase 3 adds an initial Laravel modular-monolith slice for Modules 06 (Budget Planning and Preparation), 07 (Budget Execution and Control), and 13 (General Ledger and Accounting). It reuses the existing identity/permission middleware, financial master-data tables, workflow engine, period records, audit chain, and transactional outbox. No microservices or external financial integrations were introduced.

### Module 06 — Budget Planning and Preparation

Owned tables: `budget_calls`, `budget_ceilings`, `budget_estimates`, `budget_estimate_lines`, `budget_versions`, and `budget_version_lines`. A budget officer can create a call and ceiling; planners can save and submit estimates; finance can consolidate submitted estimates into a content-hashed version. Consolidation starts the shared independent-approval workflow. Publication requires that workflow to be approved by a different user; published content is retained as an approved snapshot and later approval replaces the prior version's active status. Submissions and publication write audit and outbox records.

### Module 07 — Budget Execution and Control

Owned tables: `budget_allocations`, `budget_commitments`, and append-only `budget_commitment_movements`. Allocations can refer only to an approved budget version line and cannot exceed that line. Commitment reservation locks its allocation, checks remaining capacity, and accepts an idempotency key. Liquidation and release append movements and reduce remaining commitments; a movement cannot exceed the remaining reserved amount.

### Module 13 — General Ledger and Accounting

Owned tables: `ledger_journals` and `ledger_journal_lines`. Draft creation requires active Module 05 account combinations, one-sided debit/credit lines, and an idempotency key bound to a canonical content hash. Posting requires a shared workflow approval by another user, equal non-zero debit and credit totals, and an open accounting period. The period row is locked while posting so period close and posting serialize. Posted journal records and lines have no edit/delete API. Reversal creates a separate draft with swapped debit/credit lines and a source link to the posted journal; it must pass the same independent approval and posting controls. Trial balance reads posted journals by period.

## API and interface

Protected `/api/v1/budget/*` endpoints cover calls, ceilings, estimates, estimate submission, consolidation, publication, allocations, commitments, and commitment movements. Protected `/api/v1/ledger/*` endpoints cover journal draft creation, approval-gated posting, reversal drafts, journal listing, and trial balance. Domain events include `BudgetEstimateSubmitted`, `BudgetVersionSubmittedForApproval`, `ApprovedBudgetVersionPublished`, `BudgetCommitmentReserved`, and `JournalPosted`. A React workspace is available at `/budget-ledger`.

## Permissions and control boundaries

The phase seeds distinct permissions for call/ceiling management, estimate preparation/submission, budget consolidation/publication, allocations, commitments, journal creation/posting/reversal, and trial balance access. Shared workflow approval enforces maker-checker. The initial routes use the existing domain permission middleware. Fine-grained department-specific record filtering, configurable approval limits, commitment source adapters for Procurement/AP, releases/virements/supplements, and detailed close/reconciliation workflows still require completion before operational deployment.

## Verification record

- `php artisan migrate:fresh --seed --force` with `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` — passed; all migrations and foundation seeders including the Phase 3 migration ran successfully.
- `npm run build` — passed; TypeScript compilation and Vite production build completed.
- `php artisan route:list --path=api/v1 --except-vendor` — passed; Laravel listed 102 routes, including the Phase 3 endpoints.
- PHP syntax checks for the Phase 3 controller, migration, and API routes — passed.
- PHPUnit did not run: PHPUnit 12 reports it cannot open the configured `vendor/autoload.php` bootstrap even though the file exists and a direct PHP require succeeds. Pint check reports formatting changes, but Pint cannot write the controller in this environment. These checks are not reported as passing.

## Remaining work and unresolved decisions

- Add organizational scope enforcement to every estimate and financial record read/write, plus department-private listing behavior.
- Add API/feature tests on a working PHPUnit bootstrap, and run Pint successfully.
- Build approval/return/rejection and budget variance views, estimate ceiling variance reporting, full allocation release and transfer controls, source-module contract adapters, and complete reversal evidence/period-close readiness controls.
- Define county-approved accounting policy, budget classifications, period-close checklist, approval thresholds, and retention requirements with accountable business owners. No legal threshold or external integration has been assumed.

This record describes an initial implementation slice; it is not a claim that Modules 06, 07, or 13 are production-complete.
