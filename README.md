# FinCore County

FinCore County is a modular county public financial management platform foundation. It is not affiliated with or connected to an official county or government system. The repository now contains a Laravel API and React/TypeScript application shell; business modules and financial transactions are not implemented.

## Technology

- Laravel 13 / PHP 8.3+
- React / TypeScript / Vite, Tailwind CSS and shadcn-compatible UI primitives
- MySQL 8 and Redis for configured cache/queue services
- Laravel Sanctum API authentication foundation
- PHPUnit, Vitest, GitHub Actions

## Repository map

- [Architecture and module documentation](docs/README.md)
- [13 architecture specifications](docs/architecture/)
- [26 module specifications](docs/modules/)
- [Existing module implementation prompts](docs/module-implementation-prompts/README.md)
- [Decision log](docs/decisions/README.md)
- [Engineering standards](docs/standards/ENGINEERING_STANDARDS.md)
- [Backend application](backend/)
- [Frontend application](frontend/)

## Prerequisites

- PHP 8.3 or later with `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, and `zip` extensions.
- Composer 2.
- Node.js 24 and npm.
- MySQL 8.0+ (8.4 is used in CI).
- Redis 7+ for configured queues and cache (Laravel connects through the Composer Predis client). During local development, Redis readiness is optional unless `REDIS_REQUIRED=true`.

Docker is not required. The commands below use PowerShell syntax on Windows; use the equivalent shell commands elsewhere.

For WAMP installations where PHP is not on `PATH`, add the installed PHP directory to the current PowerShell session (adjust the version if needed):

```powershell
$env:PATH = 'D:\wamp64\bin\php\php8.3.28;' + $env:PATH
```

Install Composer 2 separately and ensure `composer` is on `PATH` before following the backend setup.

## Local setup

### 1. Backend

```powershell
cd backend
Copy-Item .env.example .env
composer install
php artisan key:generate
```

Create a local MySQL database and restricted development account (substitute a locally managed password):

```sql
CREATE DATABASE fincore_county CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fincore_app'@'localhost' IDENTIFIED BY 'use-a-local-secret';
CREATE USER 'fincore_app'@'127.0.0.1' IDENTIFIED BY 'use-a-local-secret';
GRANT ALL PRIVILEGES ON fincore_county.* TO 'fincore_app'@'localhost';
GRANT ALL PRIVILEGES ON fincore_county.* TO 'fincore_app'@'127.0.0.1';
```

Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `backend/.env`. Start MySQL, then run:

```powershell
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Run the queue worker in its own backend terminal:

```powershell
php artisan queue:work
```

Run the scheduler in a separate backend terminal:

```powershell
php artisan schedule:work
```

If using Redis, start Redis and keep `REDIS_REQUIRED=true` for readiness to enforce it. Redis queues and cache are selected by default in `.env.example`. Do not commit `.env` or credentials.

### 2. Frontend

In another terminal:

```powershell
cd frontend
Copy-Item .env.example .env
npm ci
npm run dev
```

The browser app is available at `http://localhost:5173`. Its API base URL is `VITE_API_BASE_URL`; the default points to the backend at `http://127.0.0.1:8000/api/v1`. The backend CORS allow-list is controlled by `CORS_ALLOWED_ORIGINS`.

### 3. Health checks

- Liveness: `GET http://127.0.0.1:8000/api/v1/health/live`
- Readiness: `GET http://127.0.0.1:8000/api/v1/health/ready`

Both endpoints use the standard API envelope and correlation ID. Readiness returns HTTP 503 when a required dependency is unavailable and only reports dependency names and states.

## Quality checks

Backend, from `backend/`:

```powershell
composer validate --strict
php artisan test
vendor/bin/pint --test
composer audit
```

To include local MySQL and Redis integration checks when both services are running, set `$env:RUN_MYSQL_INTEGRATION='true'`, `$env:MYSQL_INTEGRATION_DATABASE='fincore_county'` and `$env:RUN_REDIS_INTEGRATION='true'` before `php artisan test` in PowerShell.

Frontend, from `frontend/`:

```powershell
npm run typecheck
npm run lint
npm test
npm run build
npm audit --audit-level=high
```

GitHub Actions runs backend checks with MySQL 8 and Redis services, frontend checks, dependency audits, and a MySQL migration smoke check. It does not deploy the application.

## Implemented and planned

**Implemented in Phase 0:** Laravel 13 project, versioned REST routing, Sanctum package foundation, API response/error envelope, request IDs, liveness/readiness checks, MySQL/Redis configuration, CORS allow-list, rate limit baseline, React application shell, API client, accessible navigation/status/error states, UI primitives, automated tests, and CI checks.

**Not implemented:** identity lifecycle and role management, organization records, workflow approvals, financial master data, any of the remaining business modules, approval thresholds, official fiscal dates/accounting rules, real transactions, and external government/bank/payroll integrations. Follow the [implementation roadmap](docs/architecture/IMPLEMENTATION_ROADMAP.md) after Phase 0 review.

## Architecture decisions

Phase 0 provisional assumptions are recorded in [ADR-0001](docs/decisions/ADR-0001-phase-0-deployment-assumptions.md). Unresolved business and policy questions remain in [Open Business Decisions](docs/architecture/OPEN_BUSINESS_DECISIONS.md).
