# ADR-0003 Local-only anti-drift enforcement and composer verify

## Context

Podquilt already has a CI workflow built around `composer check` and `composer audit`, while the active-task packet model is intentionally repo-local and gitignored.

## Decision

Keep the anti-drift workflow local-only and make `composer verify` the local done condition. Leave CI focused on `composer check` and `composer audit` rather than trying to recreate repo-local task-state enforcement in clean checkouts.

## Consequences

Contributors and agents must run `composer context:task` and `composer verify` locally before considering work complete. CI remains useful for code health but does not prove that a local task stayed inside the planned subsystem surface.

## Validation

`composer verify` runs the code-quality checks plus the anti-drift checks, while `.github/workflows/ci.yml` continues to run the narrower CI-safe verification suite.
