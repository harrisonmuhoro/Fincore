# Business Workflow Map

Use domain events with event_id, type/version, UTC timestamp, producer, entity ID, correlation/causation IDs and minimal scope/payload. Persist through an outbox after commit; consumers must be idempotent.

| Process | Transaction owner and flow | Events and consumers |
|---|---|---|
| Plan-to-Budget | 06 owns estimates, review and approved versions; 04 records approvals | BudgetApproved → 07 |
| Budget-to-Commitment | 07 owns locked availability, commitments/releases | BudgetAllocationCreated, CommitmentCreated, CommitmentApproved → 09/12 |
| Procure-to-Pay | 09 requisition/order; 10 contract; 11 receipt; 12 invoice/voucher; 14 payment execution; 13 journal | PurchaseRequisitionApproved, PurchaseOrderIssued, GoodsReceived, SupplierInvoiceVerified, PaymentVoucherApproved, PaymentExecuted, JournalPosted |
| Revenue-to-Cash | 15 owns assessment/receipt; 14 owns bank/cash settlement and reconciliation; 13 posts | RevenueReceiptConfirmed, BankReconciliationCompleted → 13/22 |
| Record-to-Report | source domains emit accounting intent; 13 owns posting and period close; 22 reads | JournalPosted, FinancialPeriodClosed → 22 |
| Asset acquisition/disposal | 16 owns asset lifecycle; 13 accounting | AssetAcquired/Depreciated/Disposed → 13 |
| Project completion | 17 owns progress/certificates; 06/07 budget; 09/10 sourcing/contract | ProjectMilestoneUpdated → 22 |
| Payroll-to-Ledger | 19 imports/reconciles external payroll; 13 owns journal | PayrollBatchValidated, PayrollJournalRequested → 13 |
| Audit-to-remediation | 21 owns findings/actions; source module corrects source transaction | AuditFindingRaised, CorrectiveActionUpdated, AuditFindingClosed |

Workflow owns decisions, not business records. Treasury owns execution, AP vouchers, ledger posted journals, revenue receipts, and budget commitments remain in their respective owners.
