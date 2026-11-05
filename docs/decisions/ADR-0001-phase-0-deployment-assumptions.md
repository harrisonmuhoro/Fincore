# ADR-0001: Phase 0 deployment and integration assumptions

- **Status:** Accepted for development foundation; provisional pending county stakeholder review
- **Date:** 2026-11-05
- **Owners:** FinCore County architecture and product stakeholders

## Context

The approved architecture describes one Laravel modular monolith with a React client, MySQL transactional store, Redis support, private file storage and versioned REST APIs. Open business decisions include tenancy, fiscal dates, accounting rules, approval authorities and external integrations. Phase 0 needs a runnable foundation without converting unresolved policy questions into code.

## Decision

1. Build the first release for one county deployment. Model organization values as configurable references when their owning module is implemented. Do not add tenant isolation or hardcode the county's organization structure.
2. Keep one Laravel application and one MySQL database. Modules own their data and communicate through explicit contracts; no microservices are introduced.
3. Module 05 remains the authority for financial years, accounting periods and financial master data. Phase 0 creates no speculative business schema and chooses no official fiscal dates, chart of accounts, accounting basis, posting rules or statutory thresholds.
4. Treat KES only as the demonstration default stated by the architecture. It does not establish a legal settlement or reporting requirement.
5. Use isolated adapter interfaces or mocks for future external systems. Phase 0 makes no government, bank, payroll or payment-gateway connections.
6. Install Sanctum as an authentication foundation. User lifecycle, role and permission management, authorization policy and approval implementation remain future Module 02/04 scope; installation alone is not complete access control.
7. Keep Redis requiredness configurable for readiness so local application development can proceed when Redis is not installed. Production-like environments must set `REDIS_REQUIRED=true` when Redis backs required queues/cache/locks.

## Consequences

- The repository can start and be verified without implementing the first business module.
- CI exercises database migrations with MySQL and verifies Redis separately.
- No legal or financial policy should be inferred from the configuration defaults.
- Tenancy, hosting/data residency, fiscal calendar, accounting policy, role catalogue and integration authorization remain explicit stakeholder decisions.
- This ADR must be superseded or amended when an authorized business decision resolves any assumption above.

## References

- [System architecture](../architecture/SYSTEM_ARCHITECTURE.md)
- [Open business decisions](../architecture/OPEN_BUSINESS_DECISIONS.md)
- [Implementation roadmap](../architecture/IMPLEMENTATION_ROADMAP.md)
- [Module 05 — Financial master data](../modules/MODULE_05_FINANCIAL_MASTER_DATA.md)
