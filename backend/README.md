# FinCore County API

Laravel 13 API application for the FinCore County modular monolith. See the [repository setup guide](../README.md) and [architecture index](../docs/README.md).

## Local development

Install Composer dependencies, copy `.env.example` to `.env`, set local MySQL/Redis values, and generate the application key:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

The versioned API root is `/api/v1`. Liveness and readiness endpoints are `/api/v1/health/live` and `/api/v1/health/ready`.

Run checks with `php artisan test`, `vendor/bin/pint --test`, and `composer audit`.

Modules 01–05 implement identity, MFA, RBAC, organization, workflow and financial master-data foundations. See the [Phase 1 implementation record](../docs/architecture/PHASE_1_FOUNDATION_IMPLEMENTATION.md) for setup steps, API scope and known limitations. Transaction posting remains out of scope.
