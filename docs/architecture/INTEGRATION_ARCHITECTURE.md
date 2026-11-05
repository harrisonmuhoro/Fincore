# Integration Architecture

## Internal module communication
Within the modular monolith, use explicit application service interfaces for request/response operations that must complete atomically. Use versioned domain events for facts needed asynchronously by reporting, notification, audit/security telemetry or another bounded domain. Never let a consumer update producer-owned tables.

## Event envelope and reliability
Each event carries event_id, event_type, schema_version, occurred_at UTC, producer, entity_id, actor/scope references, correlation_id, causation_id and minimal payload. The producer writes event/outbox record in the same database transaction as its state change, then a queue worker publishes it. Consumers maintain dedupe/idempotency state and may safely retry. Include event version compatibility and retention.

## Queue and scheduled processing
Use Laravel Queues/Redis for delivery, imports, report builds and notifications. Jobs are idempotent, bounded-retry with backoff, observable, and move to a dead-letter/review state after exhaustion. Scheduler handles due reminders, expiring contracts/documents, escalations, recurring report jobs and monitoring checks; scheduler must use overlap prevention/locks and record run outcomes.

## External gateway
Module 25 owns connection metadata, webhook registration, transport attempts, error/retry state and adapter health. Domain owners retain transaction truth: Treasury owns executed payments, Revenue owns receipts, Procurement owns awards/POs, Payroll Integration owns imported-batch evidence, Ledger owns posted journals. Use sandbox/mock adapters until partner specifications, credentials, security review and authorization exist. No official Kenyan government, IFMIS, bank or provider integration is asserted.

## Message processing contract
Inbound webhook: authenticate/signature and timestamp, validate schema and size, deduplicate provider event, persist receipt, enqueue normalized command, return acknowledgement only after durable acceptance. Domain command validates business preconditions and idempotency. Outbound: domain emits intent, adapter transforms and sends, records provider correlation/status; uncertain outcomes enter reconciliation and are not blindly retried. Preserve raw payload only under approved retention and data minimization.

## Failure, reconciliation and monitoring
Classify retryable transport failure vs permanent validation rejection vs ambiguous business outcome. Retry only safe/idempotent operations; otherwise queue for operator reconciliation. Dead-letter records include reason and correlation IDs, not secrets. Dashboards show backlog, age, retries, error class and adapter health. Rotate credentials, redact logs and monitor webhook replay attempts.

## Current Phase 2 notification consumer

`notifications:consume-domain-events` consumes only `ApprovalCompleted` outbox events, resolves the workflow requester's ID from the workflow owner, and creates an idempotent user notification. It records a module-owned receipt and does not mark the shared outbox event as externally published. Email delivery is queued only when the recipient enables that channel. Other event subscriptions, general outbox publication, retries/dead-letter operations, and consumer projections remain Module 25/shared-platform work.
