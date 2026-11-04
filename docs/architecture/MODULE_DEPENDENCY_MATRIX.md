# Module Dependency Matrix

Hard dependencies are required for correct end-to-end execution; soft contracts permit mocked or interface-first development.

| Module | Hard dependencies | Soft/integration dependencies |
|---|---|---|
| 01 System Foundation and Configuration | All modules depend on stable configuration and shared platform contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 02 Identity and Access Management | Depends on foundation and organization reference contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 03 County Organization Management | Depends on foundation | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 04 Workflow and Approval Engine | Depends on IAM and organization scope contracts; domain modules call it through documented workflow contracts and retain ownership of their business records | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 05 Financial Master Data Management | Depends on foundation and organization references | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 06 Budget Planning and Preparation | Depends on organization, financial master data, workflow, documents and IAM contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 07 Budget Execution and Control | Depends on approved budgets, financial master data, organization, workflow and IAM | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 08 Supplier Management | Depends on foundation, IAM and document contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 09 Procurement Management | Depends on supplier, organization, workflow, documents and budget commitment contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 10 Contract Management | Depends on procurement award, supplier, documents, workflow and notification contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 11 Inventory and Stores Management | Depends on organization, procurement/PO, supplier, documents, workflow and financial master-data contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 12 Expenditure Management and Accounts Payable | Depends on supplier, procurement, contract, inventory receipt, budget control, workflow, documents, financial master data and treasury interfaces | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 13 General Ledger and Accounting | Depends on financial master data, organization, workflow, IAM and source-module event contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 14 Treasury and Cash Management | Depends on financial master data, workflow, IAM, AP payment-voucher contract and integration gateway | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 15 County Revenue Management | Depends on organization, financial master data, IAM, workflow, documents and payment/revenue gateway interfaces | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 16 Fixed Asset Management | Depends on organization, financial master data, procurement/AP, documents and workflow | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 17 Development Project Management | Depends on organization/project references, budget, procurement, contracts, documents, workflow and IAM | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 18 Grants and Transfer Management | Depends on financial master data, organization, budget, workflow, documents and treasury/ledger contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 19 Payroll Integration | Depends on IAM, financial master data, organization, integration gateway and ledger import contract | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 20 Debt and Liability Management | Depends on financial master data, documents, workflow, treasury and ledger contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 21 Audit and Compliance Management | Depends on IAM, organization, document and notification contracts; may read business records through authorized interfaces but must not own or alter them | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 22 Reporting and Business Intelligence | Depends on documented read/reporting contracts from source modules | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 23 Document and Record Management | Depends on foundation and IAM | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 24 Notification and Communication | Depends on foundation, IAM and queue contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 25 Integration and External System Gateway | Depends on foundation, IAM and audit contracts | Documented consumer interfaces; mock integrations where partner details are unavailable. |
| 26 System Audit, Security and Monitoring | Depends on foundation and IAM | Documented consumer interfaces; mock integrations where partner details are unavailable. |

Implementation layers: 01–05 platform/master foundations; 06–07 and 13 core financial control; 08–12 and 14–21 operational finance; 23,24,26 cross-cutting; 25 integration gateway; 22 reporting after source contracts. Avoid circular synchronous calls; use versioned events.
