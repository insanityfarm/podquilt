# Tooling And Quality

Podquilt treats local anti-drift verification as authoritative while keeping CI focused on the narrower code-health checks already wired through `composer check`.

## Local Toolchain

- PHP `8.5`
- Composer `2.x`
- Docker with `docker compose` for the canonical containerized workflow

## Authoritative And Derived Artifacts

Tracked source files plus `spec/` are the source of truth for current behavior and workflow.

Workflow guides live in `docs/`. Structured architecture and drift-prevention source files live under `spec/subsystems/`, `spec/decisions/`, and `glossary/terms.json`.

Generated anti-drift artifacts under `spec/generated/` plus the generated `glossary/README.md` are derived output.

These paths are local-only outputs or dependencies:

- `vendor/`
- `logs/`
- `media/`
- `.agent-context/`
- `.phpstan/`
- `.phpunit.cache/`
- `.php-cs-fixer.cache`

## Root Scripts

Code verification:

- `composer test`: fixture-driven PHPUnit regression suite
- `composer analyse`: strict PHPStan analysis
- `composer format`: PHP CS Fixer write mode
- `composer check`: tests, static analysis, and dry-run formatting checks
- `composer audit`: dependency auditing

Anti-drift workflow:

- `composer context:build`: regenerate `glossary/README.md` and files under `spec/generated/`
- `composer context:task -- "<task>"`: create the repo-local active task packet under `.agent-context/`
- `composer context:check`: enforce active-task coverage, locked-subsystem policy, and derived-artifact freshness
- `composer terms:check`: reject discouraged glossary replacements
- `composer comments:check`: reject backlog comments and reasonless `@phpstan-ignore` directives
- `composer arch:check`: enforce architecture-boundary rules
- `composer drift:review`: assemble the repo-local review packet under `.agent-context/`
- `composer verify`: run `check` plus the anti-drift checks

## Required Workflow

For local feature work, a change is not done until `composer verify` passes.

The anti-drift workflow is part of done:

- start work with `composer context:task -- "<task>"`
- regenerate context artifacts with `composer context:build` when glossary or subsystem source files change
- keep the active task packet aligned with the changed subsystem surface
- update touched subsystem records in the same change when owned code or tooling changes
- update an ADR in the same change when a locked subsystem changes

CI intentionally stays narrower. `.github/workflows/ci.yml` should continue to run `composer check` and `composer audit`, not the local-only active-task workflow.
