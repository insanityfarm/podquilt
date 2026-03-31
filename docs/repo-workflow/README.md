# Repository Workflow

This guide covers repo-wide contributor workflow that is not specific to one subsystem. The implemented contract still lives in [`../../spec/README.md`](../../spec/README.md).

## Git Commits

When a task includes creating a commit:

1. inspect recent history first with a non-interactive command such as `git log --oneline -n 10`
2. match the current repository commit-message tone, structure, and level of detail
3. prefer non-interactive git commands throughout the workflow
4. if both `git add` and `git commit` are part of the task, run them sequentially instead of in parallel

Do not treat commit messages as a place to explain implementation details that belong in spec pages, ADRs, or subsystem records.

## Local Verification

`composer verify` is the local done condition for anti-drift work.

`composer check` remains the narrower CI-safe verification command and should not silently grow local-only active-task requirements.

When a task touches authoritative workflow or architecture artifacts, include those updates in the same change rather than leaving the repo half-migrated.
