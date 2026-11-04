# Module Dependency Matrix

Hard dependencies must exist for the transaction to be safe and correct. Soft dependencies may be developed against a documented contract or mock. “Soft” never authorizes bypassing a control in production.

| # / Module | Hard dependencies | Soft / downstream contracts |
|---|---|---|
| 01 Foundation | Platform runtime, secure secret/configuration management | Provides versioning, numbering, logging, health to all modules. |
| 02 IAM | 01; organization scope contract from 03 for scoped assignments | All protected modules consume identity/policy decisions; 26 consumes security events. |
| 03 Organization | 01, 02 for secured administration | 05–22 consume effective org IDs; 17 consumes project identity. |
| 04 Workflow | 02, 03 | Domain modules call workflow contract; 24 consumes notification intents. |
| 05 Financial Master Data | 01, 02, 03, 04 for governed activation | 06–20 and 22 consume versioned finance IDs/calendar. |
| 06 Budget Planning | 02, 03, 04, 05, 23 | 07 consumes approved version; 22 reads submission/version facts. |
| 07 Budget Execution | 02, 03, 04, 05 and approved 06 budget | 09/12 request reservations and commitments; 13 consumes commitment/adjustment accounting intents. |
| 08 Suppliers | 02, 04, 23 | 09/10/12 consume supplier status; 14 receives verified beneficiary reference. |
| 09 Procurement | 02, 03, 04, 05, 07, 08, 23 | 10 receives award; 11 receives PO; 12 consumes PO/match terms; 25 optional external adapter. |
| 10 Contracts | 02, 04, 08, 09, 23, 24 | 11/12/17 consume contract milestones/schedules. |
| 11 Inventory | 02, 03, 04, 05, 09, 23 | 12 consumes accepted GRN; 13 consumes valuation intents; 22 reads stock reports. |
| 12 AP | 02, 04, 05, 07, 08, 09 where PO-backed, 10/11 when applicable, 23 | 14 consumes approved voucher; 13 consumes liability/accounting intents. |
| 13 Ledger | 02, 04, 05 and source accounting contracts | All financial source domains publish intents; 22 consumes posted/read models. |
| 14 Treasury | 02, 04, 05, 12 voucher contract, 25 adapter | 13 consumes confirmed cash events; 20/18 consume settlement references. |
| 15 Revenue | 02, 03, 04, 05, 23; 25 for electronic collection | 14 reconciles settlement; 13 posts confirmed receipts; 22 reports. |
| 16 Assets | 02, 03, 04, 05, 09/12 acquisition evidence, 23 | 13 receives accounting intents; 17 may link project reference. |
| 17 Projects | 02, 03 project identity, 04, 09/10, 23; 06/07 contracts | 22 consumes project progress; financial actuals read from 07/13. |
| 18 Grants | 02, 03, 04, 05, 06/07, 23 | 14 tracks cash settlement; 13 owns journals; 22 consumes compliance. |
| 19 Payroll Integration | 02, 03, 05, 25, 23; 04 for controlled confirmation | 13 validates/post journal; 22 receives minimized reporting projection. |
| 20 Debt | 02, 04, 05, 23 | 14 owns repayment execution; 13 owns accounting; 22 reports. |
| 21 Audit | 02, 03, 23, 24; read contracts from audited domains | Source domains perform corrections; 26 may alert on technical evidence risks. |
| 22 Reporting | 02/03 scope and read contracts from each source | 25 optional exports; no source writes. Build after source processes. |
| 23 Documents | 01 storage and 02 authorization | All modules reference document IDs and classification/retention contract. |
| 24 Notifications | 01 queue, 02 identity/preferences | Consumes domain/workflow events; no domain state dependency. |
| 25 Gateway | 01, 02, 26 audit contract | Called by 09/12/14/15/19/22 through adapter contracts; mock until approved. |
| 26 Security Monitoring | 01 observability and 02 IAM events | Consumes telemetry from all domains; module 21 may reference incident IDs, not own them. |

## Cycle prevention and transaction integrity

Use an outbox to publish after a producer transaction commits. Consumers deduplicate by event ID and business idempotency key. Do not synchronously call back into the producer during event handling. A producer remains responsible for its own state; a failed consumer retries or enters a review queue and must not silently rewrite source records.

Dependencies are intentional DAG-like capability prerequisites; where rows refer to each other operationally (e.g., module 09 asks module 07 to reserve funds), define an inbound command/response contract and avoid a dependency cycle in code.
