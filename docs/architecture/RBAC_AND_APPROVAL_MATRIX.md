# RBAC and Approval Matrix

This is a capability baseline, not a county job-title or statutory delegation schedule. Permission keys are illustrative stable identifiers; final names and authority require stakeholder approval. Apply least privilege and organization scope server-side with Laravel Policies/Gates.

| Module | Maker / operator permissions | Independent approval / privileged permissions | Separation and scope |
|---|---|---|---|
| 01 Foundation | settings.view, health.view | settings.change.approve, sequences.admin | Platform config admin cannot expose secrets or alter domain transactions. |
| 02 IAM | users.invite, accounts.manage, access.review | privileged_role.assign, account.suspend, MFA reset | Prevent self-escalation; privileged changes independently approved and logged. |
| 03 Organization | org_units.propose, leadership.assign | org_structure.activate | Effective-date changes; scoped stewardship; no self-approval of own restructure. |
| 04 Workflow | workflow.design, instance.submit | workflow.publish, decision.approve, delegation.assign | Maker-checker and no self-approval per workflow rule; versioned approver scope. |
| 05 Financial master | coa.edit, dimension.manage, period.configure | master_data.activate, period.close/reopen | Independent activation; closed-period reopening tightly restricted and audited. |
| 06 Budget planning | estimate.create, submission.submit, consolidation.prepare | budget.review, budget.approve, version.publish | Department scope; submitter cannot approve own submission. |
| 07 Budget execution | availability.check, reservation.create, commitment.release.request | commitment.approve, adjustment.approve, override.authorize | Atomic availability control; override reason and separate authority. |
| 08 Supplier | supplier.register, document.submit | supplier.verify, supplier.activate, bank_change.approve | Bank details masked; maker cannot independently verify own changes. |
| 09 Procurement | plan.manage, requisition.submit, tender.manage, evaluation.score | award.recommend, award.approve, PO.issue | Committee conflict declarations; evaluation separate from award approval; org scope. |
| 10 Contracts | contract.register, milestone.record, amendment.request | amendment.approve, contract.suspend/terminate/close | Award-linked scope; amendment authority distinct from requester. |
| 11 Inventory | receipt.record, issue.request, transfer.prepare, count.record | receipt.accept, adjustment.approve, transfer.release | Custody and stock-control separation; location/store scope. |
| 12 AP | invoice.register, match.prepare, voucher.prepare | invoice.verify, voucher.approve, dispute.resolve | Invoice preparer cannot verify/approve; treasury execution is excluded. |
| 13 Ledger | journal.prepare, reconciliation.prepare | journal.approve, journal.post, journal.reverse, period.close | Separate preparation/posting; reversal linked and approved; immutable posted entries. |
| 14 Treasury | payment_batch.prepare, statement.import, match.prepare | payment.release, payment.execute, bank_reconciliation.approve | Separate batch maker, releaser and reconciler; payment execution not implied by voucher approval. |
| 15 Revenue | tariff.propose, assessment.create, receipt.record | tariff.activate, receipt.void/refund.approve | Collector cannot approve own refund/void; collection-centre scope. |
| 16 Assets | asset.register, assignment.record, maintenance.record | capitalization.approve, disposal.approve, depreciation.post.request | Custodian cannot approve own disposal; accounting post remains module 13. |
| 17 Projects | project.propose, progress.update, certificate.submit | appraisal.approve, baseline.approve, certificate.verify, close.approve | Project scope; budget/actual ownership remains modules 06/07/13. |
| 18 Grants | grant.register, utilization.report, allocation.request | grant.activate, allocation.approve, compliance.close | Restricted-fund eligibility; disbursement execution remains Treasury. |
| 19 Payroll | batch.import, validation.prepare, reconciliation.prepare | batch.confirm, journal.request.approve | Highly restricted payroll access; source payroll remains external; GL posting separate. |
| 20 Debt | facility.register, schedule.prepare, liability.reconcile | facility.approve, schedule.change.approve | Agreement/term changes independently reviewed; treasury pays, GL posts. |
| 21 Audit | engagement.manage, evidence.collect, finding.issue, response.record | finding.close.verify, audit_report.approve | Auditors cannot alter source transactions; auditee cannot close finding; independence enforced. |
| 22 Reporting | report.run, schedule.manage, export.request | report_definition.publish, sensitive_export.approve | Source-row scope follows user; report admin cannot broaden access. |
| 23 Documents | document.upload, metadata.edit, linked_read | classification.manage, disposition.approve | Per-document and linked-record authorization; restricted evidence roles. |
| 24 Notifications | notification.read, preference.manage, announcement.draft | announcement.publish, template.activate | Delivery operators cannot alter source workflow or transaction state. |
| 25 Integrations | connection.view, message.retry.request, webhook.manage | credential.rotate, connector.activate, replay.approve | Secret access restricted; production connection activation independently approved. |
| 26 Security monitoring | security_event.read, incident.investigate, incident.acknowledge | privileged_access.review, incident.close, integrity_override | Append-only logs; no deletion or alteration by system administrators. |

## Common controls

- Org, department, cost-centre, fund, project, store, collection-centre and record sensitivity scope must be checked in backend policies on every read/write.
- Maker-checker is configurable by action risk; no self-approval where independent review is required.
- Delegations are time-bounded, scope-bounded, revocable and auditable; a delegate cannot exceed delegator authority.
- MFA is required for privileged accounts; secure sessions, revocation, failed-login monitoring and periodic access review are required.
- Financial limits and legal thresholds are configuration values pending validation, never hard-coded assumptions.
- Warning, blocked, requires-review and approved-override are distinct outcomes. Overrides need named authority, reason, timestamp, evidence and audit event; they cannot violate double-entry balance or closed-period lock.
