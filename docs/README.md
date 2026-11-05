# FinCore County Documentation Index

FinCore County is a proposed county public financial management platform inspired by common IFMIS capabilities. It makes no claim of official government ownership or integration. Phase 1 implements Modules 01–05 as platform and financial master-data foundations; transaction modules are not implemented.

## Architecture documents
- [SYSTEM_OVERVIEW](architecture/SYSTEM_OVERVIEW.md)
- [MODULE_CATALOG](architecture/MODULE_CATALOG.md)
- [MODULE_DEPENDENCY_MATRIX](architecture/MODULE_DEPENDENCY_MATRIX.md)
- [SYSTEM_ARCHITECTURE](architecture/SYSTEM_ARCHITECTURE.md)
- [DATABASE_OWNERSHIP_MATRIX](architecture/DATABASE_OWNERSHIP_MATRIX.md)
- [BUSINESS_WORKFLOW_MAP](architecture/BUSINESS_WORKFLOW_MAP.md)
- [API_ARCHITECTURE](architecture/API_ARCHITECTURE.md)
- [RBAC_AND_APPROVAL_MATRIX](architecture/RBAC_AND_APPROVAL_MATRIX.md)
- [FINANCIAL_CONTROL_SPECIFICATION](architecture/FINANCIAL_CONTROL_SPECIFICATION.md)
- [INTEGRATION_ARCHITECTURE](architecture/INTEGRATION_ARCHITECTURE.md)
- [IMPLEMENTATION_ROADMAP](architecture/IMPLEMENTATION_ROADMAP.md)
- [TESTING_STRATEGY](architecture/TESTING_STRATEGY.md)
- [OPEN_BUSINESS_DECISIONS](architecture/OPEN_BUSINESS_DECISIONS.md)

## Module specifications
- [Module 01 — SYSTEM FOUNDATION](modules/MODULE_01_SYSTEM_FOUNDATION.md)
- [Module 02 — IDENTITY AND ACCESS](modules/MODULE_02_IDENTITY_AND_ACCESS.md)
- [Module 03 — COUNTY ORGANIZATION MANAGEMENT](modules/MODULE_03_COUNTY_ORGANIZATION_MANAGEMENT.md)
- [Module 04 — WORKFLOW AND APPROVAL ENGINE](modules/MODULE_04_WORKFLOW_AND_APPROVAL_ENGINE.md)
- [Module 05 — FINANCIAL MASTER DATA](modules/MODULE_05_FINANCIAL_MASTER_DATA.md)
- [Module 06 — BUDGET PLANNING AND PREPARATION](modules/MODULE_06_BUDGET_PLANNING_AND_PREPARATION.md)
- [Module 07 — BUDGET EXECUTION AND CONTROL](modules/MODULE_07_BUDGET_EXECUTION_AND_CONTROL.md)
- [Module 08 — SUPPLIER MANAGEMENT](modules/MODULE_08_SUPPLIER_MANAGEMENT.md)
- [Module 09 — PROCUREMENT MANAGEMENT](modules/MODULE_09_PROCUREMENT_MANAGEMENT.md)
- [Module 10 — CONTRACT MANAGEMENT](modules/MODULE_10_CONTRACT_MANAGEMENT.md)
- [Module 11 — INVENTORY AND STORES MANAGEMENT](modules/MODULE_11_INVENTORY_AND_STORES_MANAGEMENT.md)
- [Module 12 — EXPENDITURE AND ACCOUNTS PAYABLE](modules/MODULE_12_EXPENDITURE_AND_ACCOUNTS_PAYABLE.md)
- [Module 13 — GENERAL LEDGER AND ACCOUNTING](modules/MODULE_13_GENERAL_LEDGER_AND_ACCOUNTING.md)
- [Module 14 — TREASURY AND CASH MANAGEMENT](modules/MODULE_14_TREASURY_AND_CASH_MANAGEMENT.md)
- [Module 15 — COUNTY REVENUE MANAGEMENT](modules/MODULE_15_COUNTY_REVENUE_MANAGEMENT.md)
- [Module 16 — FIXED ASSET MANAGEMENT](modules/MODULE_16_FIXED_ASSET_MANAGEMENT.md)
- [Module 17 — DEVELOPMENT PROJECT MANAGEMENT](modules/MODULE_17_DEVELOPMENT_PROJECT_MANAGEMENT.md)
- [Module 18 — GRANTS AND TRANSFER MANAGEMENT](modules/MODULE_18_GRANTS_AND_TRANSFER_MANAGEMENT.md)
- [Module 19 — PAYROLL INTEGRATION](modules/MODULE_19_PAYROLL_INTEGRATION.md)
- [Module 20 — DEBT AND LIABILITY MANAGEMENT](modules/MODULE_20_DEBT_AND_LIABILITY_MANAGEMENT.md)
- [Module 21 — AUDIT AND COMPLIANCE MANAGEMENT](modules/MODULE_21_AUDIT_AND_COMPLIANCE_MANAGEMENT.md)
- [Module 22 — REPORTING AND BUSINESS INTELLIGENCE](modules/MODULE_22_REPORTING_AND_BUSINESS_INTELLIGENCE.md)
- [Module 23 — DOCUMENT AND RECORD MANAGEMENT](modules/MODULE_23_DOCUMENT_AND_RECORD_MANAGEMENT.md)
- [Module 24 — NOTIFICATION AND COMMUNICATION](modules/MODULE_24_NOTIFICATION_AND_COMMUNICATION.md)
- [Module 25 — INTEGRATION AND EXTERNAL SYSTEM GATEWAY](modules/MODULE_25_INTEGRATION_AND_EXTERNAL_SYSTEM_GATEWAY.md)
- [Module 26 — SYSTEM AUDIT SECURITY AND MONITORING](modules/MODULE_26_SYSTEM_AUDIT_SECURITY_AND_MONITORING.md)

## Module implementation prompts
[Prompt index](module-implementation-prompts/README.md) and the 26 individual prompts in that directory. Prompts are preserved separately from these architecture specifications.

## Decisions and standards
- [Open business decisions](architecture/OPEN_BUSINESS_DECISIONS.md)
- [Decision log](decisions/README.md)
- [Engineering standards](standards/ENGINEERING_STANDARDS.md)

## Architecture and delivery
Laravel/PHP 8.3+, Sanctum REST, React/TypeScript/Vite, Tailwind/shadcn, MySQL 8 and Redis. Modular monolith with domain-owned data and API-first contracts. Use fictional data; KES default. See [implementation roadmap](architecture/IMPLEMENTATION_ROADMAP.md).

## Implementation records
- [Phase 1 foundation implementation, APIs, bootstrap and limitations](architecture/PHASE_1_FOUNDATION_IMPLEMENTATION.md)
