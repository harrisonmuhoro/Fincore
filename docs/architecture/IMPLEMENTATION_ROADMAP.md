# Implementation Roadmap

| Phase | Modules | Delivery milestone |
|---|---|---|
| 0 Decisions/bootstrap | Framework, tenancy, contract and accounting decisions | Secure skeleton, CI, architecture decisions |
| 1 Platform | 01–03; interfaces planned for 23,24,26 | Auth, organization scopes and configuration |
| 2 Control plane | 04,05,23,24,26 | Approval engine, financial references, documents, notification and audit contracts |
| 3 Financial core | 06,07,13 | Budget approval, concurrency-safe commitments, balanced immutable ledger, period locks |
| 4 Procure-to-pay | 08–12,14 | Requisition-to-receipt-to-voucher-to-payment and bank reconciliation |
| 5 Operational | 15–21 | Revenue, assets, projects, grants, payroll import, debt, audit remediation |
| 6 Insight/integrations | 25,22 | Mock adapters and governed reports after reliable source data |

Each phase includes schema and API review, backend-first vertical slices, frontend workflows, contract/integration tests, security review, migration checks and regression gate. Do not build dashboards before dependable source processes.
