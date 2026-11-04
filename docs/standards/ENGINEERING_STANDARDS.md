# Engineering Standards

- Modular monolith; one owner per business transaction and table set.
- Laravel controllers remain thin; validate requests and enforce Policies/Gates server-side.
- React uses strict TypeScript and accessible, functional controls.
- Version database migrations; test rollback and integrity constraints.
- Use DECIMAL, database transactions, row locks, idempotency and immutable posted journals with linked reversals.
- Apply least privilege, organization scope, maker-checker, MFA for privileged users and private validated files.
- Publish versioned event contracts through an outbox; make consumers idempotent.
- Use fictional seed/test data; secrets and real financial/personnel data do not belong in source or fixtures.
- External integrations remain mocks pending approved specs and authorization.
- Keep API documentation, tests and operational behavior aligned with implementation.
