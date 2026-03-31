# Podquilt Agent Workflow

Podquilt targets PHP 8.5 and uses Composer plus Docker as its official development workflow.

1. Read `./spec/README.md`.
2. Read `./glossary/README.md`.
3. Run `composer context:task -- "<task>"` before editing code or docs.
4. Read `.agent-context/active-task.md`.
5. Treat locked subsystems as read-only unless the active task explicitly allow-lists them.
6. Update touched authoritative artifacts in the same change: subsystem records, ADRs, spec/docs, glossary source, and generated context artifacts as needed.
7. Run `composer verify` before treating the work as done.
8. Include `Spec updates made:` in the final response, followed by the exact changed paths under `./spec/`, or `none` with a reason.
