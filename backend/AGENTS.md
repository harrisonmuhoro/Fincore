# Backend contributor guidance

- This directory is the Laravel API half of the FinCore County monolith. The React application and npm scripts live in `../frontend`.
- Read the repository [architecture index](../docs/README.md), [system architecture](../docs/architecture/SYSTEM_ARCHITECTURE.md), and [engineering standards](../docs/standards/ENGINEERING_STANDARDS.md) before adding domain code.
- Do not implement business modules unless explicitly requested. Keep this foundation free of speculative financial schemas and external integrations.
- Use MySQL/InnoDB for application migrations; use explicit ownership boundaries, reversible migrations, and database transactions where required.
- Keep API routes under `/api/v1`, use Form Requests for endpoint validation, enforce authorization server-side, and preserve the response envelope and request correlation ID.
- Run `php artisan test`, `vendor/bin/pint --test`, `composer validate --strict`, and `composer audit` for backend changes.
- Do not add Laravel Boost or another backend framework without a concrete requirement. Do not move frontend dependencies into this application.
