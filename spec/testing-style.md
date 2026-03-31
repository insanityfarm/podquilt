# Testing Style

Tests in Podquilt should prefer user-visible or contract-visible behavior over implementation details. The project is intentionally fixture-driven, so those fixtures are part of the contract surface for anti-drift review.

## Rules

- Assert what podcast clients or local contributors actually depend on.
- Prefer deterministic fixtures and injected seams over live services or wall-clock timing.
- Keep file and feed fixtures small enough that the relevant contract is obvious.
- Use temp repositories for anti-drift tooling tests that need git state, generated artifacts, or changed-file enforcement.

## Avoid

- live remote feed dependencies in automated tests
- asserting on private helper structure when the public behavior is what matters
- snapshot-style tests without a named contract reason
- broad polling or time-based waiting when a frozen clock or fixture response would be enough

## Scope

These rules apply to:

- feed-behavior tests
- config-contract tests
- rendering and golden-output tests
- anti-drift tooling tests
