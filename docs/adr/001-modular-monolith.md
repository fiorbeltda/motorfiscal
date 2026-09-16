# ADR 001 — Modular monolith

Status: Accepted.

The Fiscal Engine is implemented inside Laravel under `app/Modules/Fiscal`. Domain code may not depend on controllers, Eloquent models or external services. Service extraction requires a measured operational need.

