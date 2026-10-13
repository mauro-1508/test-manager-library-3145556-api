# test-manager-library-3145556-api

Backend of **Manager Library**, built with Laravel 12 (PHP) and a hexagonal architecture.

Specification: https://github.com/mauro-1508/test-manager-library-3145556-docs/tree/develop/spec-laravel

## Requirements

- PHP 8.2 or newer with the `pdo_pgsql` extension
- Composer 2
- PostgreSQL 16 (provided by the `infra` repository when running with Docker)
- Docker (optional, to run the API in a container)

## Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --port=8000
```

Environment variables are documented in `.env.example`. Never commit `.env`.

Check it works:

```bash
curl http://localhost:8000/api/v1/health
# {"status":"ok"}
```

## Run with Docker

```bash
docker build -t library-api .
docker run --rm -p 8000:8000 --env-file .env library-api
```

The API listens on port 8000. On start, `docker/entrypoint.sh` runs, in order:

1. `php artisan migrate --force` (the database starts empty; the migrations create the schema and seed the categories)
2. `php artisan library:ensure-admin` (creates the initial admin from `ADMIN_USERNAME` / `ADMIN_PASSWORD` if that username does not exist; the password is never printed; it fails if either variable is missing)
3. `php artisan serve --host=0.0.0.0 --port=8000`

Required environment variables: `DATABASE_URL`, `JWT_SECRET`, `ADMIN_USERNAME`, `ADMIN_PASSWORD`, `APP_KEY`, `CORS_ORIGIN`.

## Run tests

```bash
php vendor/bin/pest                          # all suites; integration tests are skipped without a database
php vendor/bin/pest --testsuite=Unit,Feature # no database needed
```

Integration tests (`tests/Integration`) run against a real PostgreSQL 16 with the migrations applied. Each test runs in a
transaction that is rolled back:

```bash
export DATABASE_URL=postgresql://library:library@localhost:5432/library
php artisan migrate --force
php vendor/bin/pest --testsuite=Integration
```
