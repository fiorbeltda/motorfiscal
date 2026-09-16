# Fiscal Engine Sandbox

Headless, deterministic and auditable fiscal decision engine built with Laravel 13 and PHP 8.5.

## Runtime

- PHP 8.5
- Laravel 13
- PostgreSQL 18
- Docker Compose

The project intentionally has no frontend in the MVP. Scenarios, rule validation, catalog imports, replay and diffs are executed through Artisan commands and JSON artifacts.

## Local environment

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

Open `http://localhost:8080` only for Laravel health checks. The Fiscal Engine interface is the command line.

## Quality gates

```bash
composer lint
composer analyse
composer test
composer audit
```

## Fiscal commands

```text
fiscal:rules:validate
fiscal:rules:publish
fiscal:catalog:import
fiscal:scenario:run
fiscal:corpus:run
fiscal:decision:replay
fiscal:decision:diff
```

See `docs/adr` for accepted architectural decisions.
