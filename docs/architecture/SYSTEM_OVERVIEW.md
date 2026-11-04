# FinCore County System Overview

FinCore County is a proposed county public financial management system for a configurable Kenyan county environment. It supports planning, budget control, procurement, revenue, treasury, accounting, assets, projects, grants, payroll imports, debt, audit and reporting. IFMIS-inspired denotes a business capability reference only; this repository makes no claim of government ownership, approval or integration.

## Users and outcomes
Departmental requesters, budget and finance officers, procurement/store/revenue teams, approvers, treasury, auditors, administrators and executives need traceable workflows, controlled spending, reconciled collections and dependable reports. Roles and delegated authority remain county decisions.

## Technology and boundaries
Laravel/PHP 8.3+, Sanctum, REST API, Policies/Gates, queues; React/TypeScript/Vite/Tailwind/shadcn; MySQL 8, Redis and private document storage. Modular monolith with domain-owned data and explicit contracts. KES is default currency. External bank, e-GP, payroll, payment and government reporting remain external; use mocks until specifications, credentials and authorization exist. Payroll is integration-first.

## Repository and implementation status
The repository contains a root README, documentation indexes, architecture documents, module specifications, implementation prompts, decisions and engineering standards. The inspected repository does not contain Laravel backend source, React frontend source, database migrations or implemented business functionality. These documents are a proposed blueprint, not evidence of a deployed or legally validated system.

## Integrity
All monetary storage uses MySQL DECIMAL and decimal-safe calculations. Financial writes use transactions and concurrency controls; budget reservations, commitments, actual expenditure and releases are distinct. Journals balance before posting; posted entries are immutable and corrected by linked authorized reversals/adjustments. Closed periods reject posting unless an approved reopening process exists. Payment commands are idempotent; uncertain external outcomes are reconciled before retry. Use fictional seed data only.
