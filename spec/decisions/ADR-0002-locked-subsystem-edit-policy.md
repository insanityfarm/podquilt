# ADR-0002 Locked subsystem edit policy

## Context

Podquilt contains seams that should be extended carefully rather than casually rewritten, especially the public config contract, feed-selection semantics, and rendering contract.

## Decision

Mark those subsystems as `locked` in their subsystem records. Any task that intends to edit locked subsystem code must opt in through the repo-local active task packet, and the same change must update the touched subsystem record plus a related ADR under `spec/decisions/`.

## Consequences

Routine feature work fails closed when it spills into locked subsystem code. Explicit architecture work remains possible, but it must be visible in the authoritative records.

## Validation

`composer context:check` reads the active task packet and rejects locked subsystem changes without an allow-list entry and matching authoritative record updates.
