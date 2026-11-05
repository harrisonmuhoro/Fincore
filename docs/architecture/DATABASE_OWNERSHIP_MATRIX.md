# Database Ownership Matrix

Candidate schema groups and current implementation ownership. The Phase 3 tables listed below are implemented by Laravel migrations; other module groups remain specifications until their phase is delivered. Use MySQL 8, stable keys selected at bootstrap, explicit status/effective dates, DECIMAL monetary columns, indexes for scope/status/date, and unique reference/idempotency keys. Avoid cascading deletion of financial/audit records.

| Owner | Candidate owned tables |
|---|---|
| 01 System Foundation and Configuration | settings, reference_sequences, platform_parameters |
| 02 Identity and Access Management | users, roles, permissions, scopes |
| 03 County Organization Management | organizational_units, cost_centres, programmes, project identities |
| 04 Workflow and Approval Engine | workflow_definitions, steps, instances, decisions |
| 05 Financial Master Data Management | accounts, funds, funding_sources, economic_classifications, dimension_types, dimensions, account_combinations, accounting_rules, currencies, financial_calendars, financial_years, accounting_periods, period_transition_history, master_data_versions |
| 06 Budget Planning and Preparation | `budget_calls`, `budget_ceilings`, `budget_estimates`, `budget_estimate_lines`, `budget_versions`, `budget_version_lines` |
| 07 Budget Execution and Control | `budget_allocations`, `budget_commitments`, `budget_commitment_movements` (release/liquidation movements; full release/virement/encumbrance lifecycle remains pending) |
| 08 Supplier Management | suppliers, documents, restricted bank details |
| 09 Procurement Management | procurement_plans, requisitions, tenders, purchase_orders |
| 10 Contract Management | contracts, amendments, milestones, schedules |
| 11 Inventory and Stores Management | stock_items, stores, receipts, movements, stocktakes |
| 12 Expenditure Management and Accounts Payable | supplier_invoices, invoice_lines, vouchers, credit_notes |
| 13 General Ledger and Accounting | `ledger_journals`, `ledger_journal_lines` (posting batches and close/reconciliation runs remain pending) |
| 14 Treasury and Cash Management | bank_accounts, cashbook, payment_batches, statements, reconciliations |
| 15 County Revenue Management | revenue_streams, tariffs, assessments, invoices, receipts |
| 16 Fixed Asset Management | assets, assignments, maintenance, depreciation, disposals |
| 17 Development Project Management | development_projects, milestones, progress, certificates |
| 18 Grants and Transfer Management | grants, conditions, allocations, utilization |
| 19 Payroll Integration | payroll_imports, protected summaries, mappings, reconciliations |
| 20 Debt and Liability Management | debt_facilities, agreements, schedules, liabilities |
| 21 Audit and Compliance Management | audit_plans, findings, responses, corrective_actions, risks |
| 22 Reporting and Business Intelligence | report_definitions, schedules, governed read models |
| 23 Document and Record Management | documents, versions, classifications, links |
| 24 Notification and Communication | notifications, attempts, preferences |
| 25 Integration and External System Gateway | integration_connections, messages, webhooks |
| 26 System Audit, Security and Monitoring | security_events, audit index, incidents |

Cross-module IDs reference the owning domain; no duplicate editable master. Financial year/period ownership is module 05; project identity belongs to 03 while delivery lifecycle belongs to 17. Define key format, tenancy, precision, retention, outbox and audit immutability before migrations.
