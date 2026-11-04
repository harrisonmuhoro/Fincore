# FinCore County Documentation

FinCore County is a proposed enterprise county PFM system. This repository currently contains architectural documentation and implementation prompts; no Laravel/React business modules are implemented.

## Navigation
- [Architecture](architecture/SYSTEM_OVERVIEW.md)
- [Module catalog](architecture/MODULE_CATALOG.md)
- [Dependencies](architecture/MODULE_DEPENDENCY_MATRIX.md)
- [Database ownership](architecture/DATABASE_OWNERSHIP_MATRIX.md)
- [Workflow map](architecture/BUSINESS_WORKFLOW_MAP.md)
- [Module specifications](modules/)
- [Decisions](decisions/)
- [Standards](standards/)
- [Implementation prompts](module-implementation-prompts/README.md)

## Stack and approach
Laravel/PHP 8.3+, Sanctum, REST, React/TypeScript, Vite, Tailwind/shadcn, MySQL 8, Redis; modular monolith with domain ownership. KES default; fictional development data only. External integrations remain mocked until approved.

## Roadmap and status
See architecture implementation roadmap. Current status: documentation planning; application framework and business features are not present.
