# Architecture Decision Records

Accepted decisions for the headless Fiscal Engine MVP:

1. Laravel 13 and PHP 8.5 are the application and engine runtime.
2. The engine is a modular monolith and has no tenant concept.
3. PostgreSQL 18 is the production-shaped persistence layer.
4. Fiscal knowledge is versioned JSON validated by JSON Schema.
5. Runtime rules are compiled immutable PHP objects.
6. Decisions are deterministic, traceable and replayable.
7. External sources are consumed through versioned local snapshots.
8. The MVP is headless and exposes Artisan commands plus JSON files.

