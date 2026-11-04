# Implementation Roadmap

| Phase | Modules | Delivery milestone |
|---|---|---|
| 0 Decisions/bootstrap | Framework, tenancy, contract and accounting decisions | Secure skeleton, CI, architecture decisions |
| 1 Platform foundation | 01–05 | Configuration, identity, organization, reusable approvals and financial master data; this is the minimum first release foundation |
| 2 Shared controls | 23,24,26 | Private document service, notification delivery and security/audit monitoring contracts |
| 3 Financial core | 06,07,13 | Budget approval, concurrency-safe commitments, balanced immutable ledger, period locks |
| 4 Procure-to-pay | 08–12,14 | Requisition-to-receipt-to-voucher-to-payment and bank reconciliation |
| 5 Operational | 15–21 | Revenue, assets, projects, grants, payroll import, debt, audit remediation |
| 6 Insight/integrations | 25,22 | Mock adapters and governed reports after reliable source data |

Each phase includes schema and API review, backend-first vertical slices, frontend workflows, contract/integration tests, security review, migration checks and regression gate. Procurement, AP, treasury and ledger remain distinct owners through procure-to-pay. Do not build dashboards before dependable source processes.
