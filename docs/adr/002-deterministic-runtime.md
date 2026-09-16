# ADR 002 — Deterministic runtime

Status: Accepted.

Evaluation receives every date, ruleset and catalog version explicitly. Rules cannot read the system clock, database or network. Equal canonical inputs and versions must produce the same decision hash.

