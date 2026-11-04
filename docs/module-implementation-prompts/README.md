# FinCore County module implementation prompts

This directory contains one implementation prompt for each of the 26 FinCore County modules. Use a prompt only after its hard dependencies and the system architecture contracts are available. Each prompt instructs the implementer to inspect the current repository, follow existing conventions, and report blockers or missing business decisions rather than inventing requirements.

## Suggested implementation sequence

1. System Foundation and Configuration; Identity and Access Management; County Organization Management.
2. Workflow and Approval Engine; Financial Master Data Management; Document and Record Management; Notification and Communication; System Audit, Security and Monitoring.
3. Budget Planning and Preparation; Budget Execution and Control; General Ledger and Accounting.
4. Supplier Management; Procurement Management; Contract Management; Inventory and Stores Management; Expenditure Management and Accounts Payable; Treasury and Cash Management.
5. County Revenue Management; Fixed Asset Management; Development Project Management; Grants and Transfer Management; Payroll Integration; Debt and Liability Management; Audit and Compliance Management.
6. Integration and External System Gateway; Reporting and Business Intelligence.

This is a suggested sequence, not a substitute for the dependency matrix. Treat undocumented dependencies as blockers to resolve through interface contracts or mock adapters.
