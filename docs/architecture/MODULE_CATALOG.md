# Module Catalog

| # | Module | Authoritative ownership |
|---|---|---|
| 01 | System Foundation and Configuration | Own application configuration, API versioning, global settings, reference number generation, financial year configuration, system parameters, logging infrastructure, error handling, shared utilities, and health monitoring |
| 02 | Identity and Access Management | Own authentication, user and account lifecycle, password management, MFA, roles, permissions, organizational access scopes, user assignments, sessions, suspension, and access history |
| 03 | County Organization Management | Own configurable county structures: departments, directorates, sub-counties, wards, cost centres, programmes, projects, hierarchy, and reporting structures |
| 04 | Workflow and Approval Engine | Own configurable workflows, approval chains and limits, delegation, rejection/return, escalation, notifications integration, workflow history, and segregation-of-duties enforcement |
| 05 | Financial Master Data Management | Own chart of accounts, funds, funding sources, economic classifications, dimensions, account combinations, accounting rules, financial periods, and financial-year definitions |
| 06 | Budget Planning and Preparation | Own budget calendars, departmental estimates/submissions, ceilings, recurrent and development budgets, programme-based budgeting, consolidation, review, approval, and approved versions |
| 07 | Budget Execution and Control | Own allocations, releases, availability checks, commitments, encumbrances, liquidation, adjustments, virements, supplementary budgets, and utilization reporting |
| 08 | Supplier Management | Own supplier registration/profile/classification/verification/documents/status/performance and transaction-history projections; protect restricted bank details |
| 09 | Procurement Management | Own annual procurement plans, requisitions, procurement methods, tenders, quotations, evaluations, approvals, purchase orders, awards and procurement reporting; define e-GP boundary |
| 10 | Contract Management | Own contract registration/amendments, milestones, deliverables, contractor performance, payment schedules, expiry notifications and closure |
| 11 | Inventory and Stores Management | Own inventory categories/items, warehouses/stores, goods received notes, issues, transfers, adjustments, stocktakes, reorder levels and valuation |
| 12 | Expenditure Management and Accounts Payable | Own supplier invoices, validation, three-way matching, verification, payables, payment vouchers, approvals, supplier balances, bills, aging and credit notes |
| 13 | General Ledger and Accounting | Own double-entry journals, automated journal generation, journal approval/posting, trial balance, general ledger, reconciliations, adjustments, reversals, period/year close and statements |
| 14 | Treasury and Cash Management | Own bank account registry, cashbook, cash-flow management, payment batches, execution tracking, statement imports, reconciliation, cash position and treasury reports |
| 15 | County Revenue Management | Own revenue streams/tariffs, assessments, invoices, receipts, payments, collection centres/officers, arrears, reconciliation and revenue reporting |
| 16 | Fixed Asset Management | Own asset register/classification/tagging/acquisition/assignment/transfers/maintenance/depreciation/valuation/disposal |
| 17 | Development Project Management | Own county development projects, project budgets, contractors, milestones, progress, certificates, expenditure views and performance reports |
| 18 | Grants and Transfer Management | Own grant registration, conditional/unconditional grants, intergovernmental transfers, allocations, disbursements, utilization, compliance and reporting |
| 19 | Payroll Integration | Own payroll imports, validation, control totals, deductions mapping, journal generation requests, reconciliation and payroll reporting; integration-first, not payroll replacement |
| 20 | Debt and Liability Management | Own county debt records, loan agreements, repayment schedules, interest records, liability registers, obligations and debt reporting |
| 21 | Audit and Compliance Management | Own internal audit plans/assignments/findings/evidence/recommendations/management responses/corrective actions/risk registers/compliance monitoring/reports |
| 22 | Reporting and Business Intelligence | Own dashboards, budget/revenue/expenditure/procurement/cash-flow/financial/audit reports, custom report builder, scheduled reports and exports |
| 23 | Document and Record Management | Own secure uploads, classification, versioning, access permissions, linking, evidence preservation, retention policies and secure downloads |
| 24 | Notification and Communication | Own in-app/email notifications, approval/escalation/payment/budget alerts, announcements and preferences |
| 25 | Integration and External System Gateway | Own banking/payment/e-GP/payroll/revenue/government-reporting adapters, webhooks, credentials, monitoring, retries, error handling and integration audit logs |
| 26 | System Audit, Security and Monitoring | Own business/security event monitoring, login and failed-auth monitoring, privileged-action monitoring, suspicious-activity detection, application/backup/error monitoring and audit-log integrity controls |

Domain modules own their transaction records. Shared IAM, workflow, document, notification, gateway, monitoring and reporting capabilities do not become duplicate transaction ledgers. See each specification in ../modules/.
