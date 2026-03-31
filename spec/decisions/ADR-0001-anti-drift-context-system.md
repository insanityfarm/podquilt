# ADR-0001 Anti-drift context system

## Context

Podquilt needs a compact, queryable way to keep blank-context work aligned with implemented architecture and workflow.

## Decision

Store durable architecture memory in structured subsystem records, glossary source data, generated repo maps, and short ADRs. Keep `AGENTS.md` thin and use it to route readers into the authoritative artifacts and the task briefing workflow instead of carrying the full project memory itself.

## Consequences

Task work starts from a generated task brief and updates relevant subsystem records, ADRs, and docs when behavior or workflow changes. Generated context artifacts become derived outputs that must stay in sync with their JSON and Markdown sources.

## Validation

`composer context:build`, `composer context:check`, `composer terms:check`, `composer comments:check`, `composer arch:check`, and `composer verify` enforce the structure locally.
