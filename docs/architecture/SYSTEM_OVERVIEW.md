# FinCore County System Overview

FinCore County is a proposed county public financial management system for a configurable Kenyan county environment. It supports planning, budget control, procurement, revenue, treasury, accounting, assets, projects, grants, payroll imports, debt, audit and reporting. IFMIS-inspired denotes a business capability reference only; this repository makes no claim of government ownership, approval or integration.

## Users and outcomes
Departmental requesters, budget and finance officers, procurement/store/revenue teams, approvers, treasury, auditors, administrators and executives need traceable workflows, controlled spending, reconciled collections and dependable reports. Roles and delegated authority remain county decisions.

## Technology and boundaries
Laravel/PHP 8.3+, Sanctum, REST API, Policies/Gates, queues; React/TypeScript/Vite/Tailwind/shadcn; MySQL 8, Redis and private document storage. Modular monolith with domain-owned data and explicit contracts. KES is default currency. External bank, e-GP, payroll, payment and government reporting remain external; use mocks until specifications, credentials and authorization exist. Payroll is integration-first.

## Integrity and status
All amounts use DECIMAL, financial writes use transactions and concurrency controls, sensitive actions are idempotent, journals balance, posted entries are immutable and corrected by reversal/adjustment, closed periods lock. Fictional seed data only. Repository inspection found no application source or README; documentation is a blueprint, not implemented software.
