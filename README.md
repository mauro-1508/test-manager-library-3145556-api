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

The API listens on port 8000.

## Run tests

```bash
php vendor/bin/pest
```

The tests currently available do not need a database.
