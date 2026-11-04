# FinCore County Module Catalog

The platform is a modular monolith. Each row identifies one authoritative domain. Cross-module use is by stable ID, application contract or versioned event; a consumer does not write the producer's records.

| # | Module | Authoritative ownership and boundary |
|---|---|---|
| 01 | System Foundation and Configuration | Environment-independent settings, global parameters, API conventions, numbering and health. Does not own financial year/period. |
| 02 | Identity and Access Management | User identities, credentials, roles, permissions, scopes, sessions and access events. |
| 03 | County Organization Management | County organizational hierarchy, effective-dated leadership, cost-centres, programmes and project identity. Module 17 owns project delivery. |
| 04 | Workflow and Approval Engine | Workflow definitions, versioning, instances, assignments, decisions and delegation. Does not own submitted business transactions. |
| 05 | Financial Master Data Management | Chart, funds, classifications, dimensions, account combinations, accounting rules, financial years, periods and calendar status. |
| 06 | Budget Planning and Preparation | Budget call, ceilings, estimates, submissions, consolidation, review and approved budget versions. |
| 07 | Budget Execution and Control | Approved-budget allocations/releases, reservations, commitments, encumbrances, liquidation, transfers and execution balances. |
| 08 | Supplier Management | Supplier identity, due diligence, status, classifications, verified bank details and performance history. |
| 09 | Procurement Management | Procurement plans, requisitions, sourcing, bids, evaluation, recommendation, award and purchase orders. |
| 10 | Contract Management | Award-linked contracts, amendments, obligations, milestones, deliverables, performance and closure. |
| 11 | Inventory and Stores Management | Stores, item catalogue, receipts, inspections, stock movements, counts, valuation and exceptions. |
| 12 | Expenditure and Accounts Payable | Supplier invoices, matching, liabilities, credit notes, disputes and approved payment vouchers. Treasury executes payment. |
| 13 | General Ledger and Accounting | Journal records, validation, posting, ledger, reconciliation, adjustment/reversal, trial balance and close. |
| 14 | Treasury and Cash Management | Bank registry, cashbook, payment batch/execution outcome, statement import and bank reconciliation. |
| 15 | County Revenue Management | Revenue sources/tariffs, assessments, billing, receipts, allocation, arrears, refunds and revenue reconciliation. |
| 16 | Fixed Asset Management | Asset register, tagging, custody, maintenance, depreciation, impairment, verification and disposal lifecycle. |
| 17 | Development Project Management | Project proposal/appraisal, baselined delivery, milestones, certificates, progress, variations and closure. Budget actuals remain 07/13. |
| 18 | Grants and Transfer Management | Grant agreements, conditions, allocations, utilization/compliance and transfer tracking. Treasury owns cash execution. |
| 19 | Payroll Integration | External payroll batch ingestion, validation, mapping, control totals, reconciliation and journal request; not a payroll engine. |
| 20 | Debt and Liability Management | Facilities, agreements, principal/liability records, interest and repayment schedules. Treasury pays, ledger posts. |
| 21 | Audit and Compliance Management | Audit plans/engagements, working evidence references, findings, responses and remediation closure. |
| 22 | Reporting and Business Intelligence | Governed read models, definitions, schedules, exports and freshness/reconciliation indicators; read-only to sources. |
| 23 | Document and Record Management | Private file metadata, versions, classification, links, holds, retention and disposition evidence. |
| 24 | Notification and Communication | Notification templates/preferences, announcements and delivery attempts; never approval or transaction state. |
| 25 | Integration and External System Gateway | External adapter configuration, inbound/outbound transport, retries and delivery monitoring; no domain transaction ownership. |
| 26 | System Audit, Security and Monitoring | Canonical technical/security telemetry, monitoring incidents and integrity signals; module 21 owns audit engagements/findings. |

## Ownership conflict resolutions

- Module 05 is the sole owner of financial years, accounting periods, calendar status and period transition history. Module 01 may provide generic system settings and numbering but consumes calendar references from 05.
- Module 03 owns project identity/classification. Module 17 owns proposal-to-delivery lifecycle. Budget modules own financial baselines/actual controls.
- Module 12 owns invoice liability and payment voucher approval. Module 14 owns release/execution outcome and bank reconciliation. Module 13 owns posted journals.
- Module 21 owns audit findings/remediation; module 26 owns system/security incidents and telemetry.
- Module 04 owns approval decisions, not the business record. Module 24 owns delivery, not approval state.
