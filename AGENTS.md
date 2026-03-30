# Podquilt Agent Workflow

Podquilt targets PHP 8.5 and uses Composer plus Docker as its official development workflow.

## Canonical Commands

- Install dependencies with `docker compose run --rm app composer install` or `composer install`.
- Start the app with `docker compose up` or `composer serve`.
- Run the required verification suite with `docker compose run --rm app composer check` or `composer check`.
- Run dependency auditing with `docker compose run --rm app composer audit` or `composer audit`.

## Repo Expectations

- Preserve the existing `config.json` schema and current feed behavior unless the user explicitly asks for a breaking change.
- Keep tests fixture-driven; automated tests must not rely on live remote feeds.
- Prefer the tracked Docker and Composer workflows. Do not reintroduce ad hoc helper scripts.
- Do not commit `config.json`, `vendor/`, logs, generated media, or local tool caches.

## Implementation Guidance

- Add or update regression tests before changing business logic.
- Keep comments high-signal: explain invariants, side effects, and non-obvious reasoning.
- Favor typed immutable value objects, injected seams, and PHP 8.5-native APIs over ambient globals and dynamic state.
