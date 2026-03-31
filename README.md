# Podquilt

Podquilt turns a pile of podcast feeds into one calm, curated feed that feels like it was made just for you. Point it at the shows you care about, hide the trailer noise, cap each source to a sane number of recent episodes, replay an archive on a schedule, and even sprinkle in your own file-backed audio links. The result is plain old RSS, which means your podcast app keeps working exactly the way you expect.

It is especially useful when your listening habits do not line up with how publishers ship their feeds. Maybe you want a single "morning queue" made from several shows. Maybe you love an old program and want it to reappear one episode at a time every Monday. Maybe you need a private feed that mixes public podcasts with a few direct MP3 links. Podquilt is built for that kind of practical feed reshaping without asking you to move into a hosted platform.

Behind the scenes, Podquilt parses and rewrites feeds with PHP 8.5's DOM support, validates URLs with PHP 8.5's URI support, fetches remote feeds concurrently for better responsiveness, and produces the same merged feed for the same config.

## Requirements

Podquilt is intentionally lightweight. You need:

- PHP 8.5
- Composer 2
- PHP extensions: `dom`, `hash`, `json`, and `libxml`
- Docker Desktop with `docker compose` if you want the containerized workflow

Docker is the easiest way to get a predictable environment. A system-PHP workflow is equally supported if you already have PHP 8.5 and Composer installed locally.

## Quick Start

Start by copying the example config:

```bash
cp config.json.example config.json
```

Then edit `config.json` with your own feed URLs and preferences.

If you want the most reproducible setup, use Docker:

```bash
docker compose run --rm app composer install
docker compose up
```

Podquilt will be available at [http://127.0.0.1:8000](http://127.0.0.1:8000).

If you prefer to run directly on your machine:

```bash
composer install
composer serve
```

Either way, the app reads `config.json`, fetches your configured feeds, and emits a single RSS document from the project root.

## Everyday Development

Composer is the public task runner for the project. The most useful commands are:

- `composer serve` starts the PHP built-in server on port `8000`
- `composer test` runs the fixture-driven PHPUnit regression suite
- `composer analyse` runs PHPStan with the project's strict configuration
- `composer format` applies the PHP CS Fixer rules
- `composer check` runs tests, static analysis, and a dry-run formatting check
- `composer context:build` regenerates tracked anti-drift artifacts
- `composer context:task -- "<task>"` writes the repo-local active task packet
- `composer terms:check` rejects discouraged glossary replacements
- `composer comments:check` rejects backlog comments and reasonless `@phpstan-ignore` directives
- `composer arch:check` rejects forbidden internal dependency edges
- `composer drift:review` writes a bounded drift-review packet under `.agent-context/`
- `composer verify` runs `check` plus the anti-drift checks
- `composer audit` checks dependencies for published security advisories

With Docker, the same commands are available through `docker compose run --rm app composer ...`, for example:

```bash
docker compose run --rm app composer check
docker compose run --rm app composer audit
```

## Anti-Drift Workflow

Podquilt keeps its architecture memory inside the repository instead of leaving it implicit in prompts or contributor memory.

For local feature work, the daily flow is:

1. Read [`spec/README.md`](./spec/README.md).
2. Read [`glossary/README.md`](./glossary/README.md).
3. Run `composer context:task -- "<task>"`.
4. Read `.agent-context/active-task.md`.
5. Update code plus any touched subsystem records, ADRs, and docs in the same change.
6. Run `composer context:build` if glossary or subsystem source files changed.
7. Run `composer verify`.

`composer verify` is the local done condition for anti-drift work. `composer check` remains the narrower CI-safe command used by the repository workflow.

## Configuration Guide

Podquilt's public schema lives in `config.json`. The file is intentionally small, but a few keys have precise behavior that is worth understanding before you start tuning it.

At the top level, Podquilt recognizes five sections:

- `feeds`
- `files`
- `channel`
- `logs`
- `http`

Unknown keys are ignored.

### `feeds`

The `feeds` section is where most of the personality of a Podquilt instance lives. Each entry describes one remote RSS feed and the rules Podquilt should apply when deciding which items from that feed belong in the merged output.

Supported keys:

- `url`: string, required in practice. Must be an absolute `http` or `https` URL.
- `prepend`: string or omitted. If present and non-empty, Podquilt prefixes each selected item's direct `title` and direct `itunes:title` nodes when present.
- `item_limit`: integer or omitted. Defaults to `10`. Values above `10` are clamped to `10`.
- `item_max_age`: integer or omitted. Defaults to `14`. Values above `14` are clamped to `14`.
- `filter`: object keyed by RSS node name. Each value is treated as a case-insensitive regular expression.
- `replay`: object or omitted. When present, replay scheduling is enabled for that source.
- `disabled`: string or omitted. Only the literal string `"true"` disables the feed.

Some details matter:

- Filters are evaluated against the parsed item field map. If a field exists and does not match its regex, the item is excluded.
- If a filtered field is missing entirely, Podquilt does not exclude the item for that reason alone.
- Replay reverses the item order for scheduling, rewrites `pubDate`, and respects item limits and age checks.

Example:

```json
{
  "feeds": [
    {
      "url": "https://feeds.npr.org/510318/podcast.xml",
      "prepend": "Up First: ",
      "item_max_age": 3
    },
    {
      "url": "https://feeds.99percentinvisible.org/99percentinvisible",
      "prepend": "99% Invisible: ",
      "item_limit": 5,
      "filter": {
        "title": "^((?!Service Request).)*$"
      }
    }
  ]
}
```

In plain English, that example says: "Keep only the last few days of Up First, make the titles easy to spot in my player, and also pull in 99% Invisible while skipping episodes whose titles contain `Service Request`."

Replay example:

```json
{
  "feeds": [
    {
      "url": "https://feeds.megaphone.fm/search-engine",
      "replay": {
        "schedule": "0 9 * * 1",
        "replayStartDate": "Mon, 06 Jan 2025 09:00:00 +0000",
        "originalStartDate": "Mon, 06 Jan 2020 09:00:00 +0000"
      },
      "disabled": "true"
    }
  ]
}
```

That configuration shows the `replay` syntax together with a disabled feed example. It keeps the feed turned off until you are ready, then replays older episodes on the supplied cron schedule starting from the replay start date.

### `files`

The `files` section lets you create synthetic RSS items from direct media URLs. This is useful when you want to tuck a private recording, a special one-off audio file, or a manually hosted episode into the same feed as your remote sources.

Supported keys:

- `url`: string, required in practice. Must be an absolute URI.
- `title`: string, required in practice.
- `pubDate`: string, required in practice. Must be an RSS timestamp such as `Sun, 29 Mar 2026 22:30:00 +0000`.
- `description`: string, required in practice.
- `disabled`: string or omitted. Only the literal string `"true"` disables the file item.

Synthetic file items are subject to Podquilt's built-in age window. Items older than 14 days, or dated in the future, are skipped to match the rest of the feed-merging logic.

Example:

```json
{
  "files": [
    {
      "url": "https://example.com/audio/private-briefing.mp3",
      "title": "Private Briefing (replace with your own audio URL)",
      "pubDate": "Sun, 29 Mar 2026 22:30:00 +0000",
      "description": "Example of a synthetic file-backed item. Replace this URL and metadata with your own hosted audio."
    }
  ]
}
```

This is a nice fit for small personal publishing workflows: host an MP3 somewhere stable, describe it in `config.json`, and it becomes another item in the merged feed.

### `channel`

The `channel` section controls the metadata of the final combined RSS feed. It is small, but it shapes how the feed appears in podcast apps.

Supported keys:

- `title`: string, optional. Defaults to `Podquilt`.
- `link`: string, optional. Defaults to the current request host and path.
- `description`: string, optional. Defaults to `Your description here.`

Example:

```json
{
  "channel": {
    "title": "My Morning Queue",
    "link": "https://podquilt.example.com/feed",
    "description": "A single feed for the shows I actually want to keep up with."
  }
}
```

If you are sharing the merged feed with another person, this is the section that makes it feel polished instead of anonymous.

### `logs`

The `logs` section controls Podquilt's file-backed operational logging. Logs are useful for catching invalid URLs, replay misconfiguration, and upstream feed failures without dumping noise into the RSS response itself.

Supported keys:

- `enabled`: boolean, optional. Defaults to `true`.
- `level`: integer, optional. Defaults to `1`.
- `path`: string, optional. Defaults to `logs/podquilt.log`.

Log levels are numeric:

- `1`: errors only
- `2`: errors and warnings
- `3`: errors, warnings, and notices
- `4`: errors, warnings, notices, and informational messages

Relative paths are resolved from the project root. Absolute paths are used as-is.

Example:

```json
{
  "logs": {
    "enabled": true,
    "level": 4,
    "path": "logs/podquilt.log"
  }
}
```

That setting is handy during development because it records the full story of what Podquilt did for each request, including concurrent feed fetch activity and configuration warnings.

### `http`

The `http` section contains runtime tuning for remote feed fetching.

Supported keys:

- `max_concurrent_requests`: positive integer, optional. Defaults to `6`.

Podquilt fetches remote feeds concurrently. This setting caps how many feed requests Podquilt will keep in flight at once.

Behavior notes:

- Omitting the key uses the default of `6`.
- Invalid, zero, or negative values fall back to `6`.
- When a fallback happens, Podquilt logs a warning instead of failing the request.

Example:

```json
{
  "http": {
    "max_concurrent_requests": 4
  }
}
```

That is a good conservative setting when you have a modest number of feeds and want a little parallelism without being too aggressive. If you aggregate a larger set of shows, raising it can reduce total response time, but the ideal value depends on how quickly your upstream feeds answer.

## Full Example

Here is a representative configuration that uses every public section:

```json
{
  "feeds": [
    {
      "url": "https://feeds.npr.org/510318/podcast.xml",
      "prepend": "Up First: ",
      "item_max_age": 3
    },
    {
      "url": "https://feeds.npr.org/510289/podcast.xml",
      "prepend": "Planet Money: ",
      "item_limit": 5
    },
    {
      "url": "https://feeds.99percentinvisible.org/99percentinvisible",
      "prepend": "99% Invisible: ",
      "item_limit": 5,
      "filter": {
        "title": "^((?!Service Request).)*$"
      }
    },
    {
      "url": "https://feeds.megaphone.fm/search-engine",
      "prepend": "Search Engine: ",
      "replay": {
        "schedule": "0 9 * * 1",
        "replayStartDate": "Mon, 06 Jan 2025 09:00:00 +0000",
        "originalStartDate": "Mon, 06 Jan 2020 09:00:00 +0000"
      },
      "disabled": "true"
    }
  ],
  "files": [
    {
      "url": "https://example.com/audio/private-briefing.mp3",
      "title": "Private Briefing (replace with your own audio URL)",
      "pubDate": "Sun, 29 Mar 2026 22:30:00 +0000",
      "description": "Example of a synthetic file-backed item. Replace this URL and metadata with your own hosted audio."
    },
    {
      "url": "https://example.com/audio/bonus-episode.mp3",
      "title": "Disabled File Example",
      "pubDate": "Sat, 28 Mar 2026 22:30:00 +0000",
      "description": "Example of a disabled file-backed item.",
      "disabled": "true"
    }
  ],
  "channel": {
    "title": "My Morning Queue",
    "link": "https://podquilt.example.com/feed",
    "description": "A single feed for the shows I actually want to keep up with."
  },
  "logs": {
    "enabled": true,
    "level": 4,
    "path": "logs/podquilt.log"
  },
  "http": {
    "max_concurrent_requests": 6
  }
}
```

See [config.json.example](config.json.example) for the tracked example file that ships with the repository.

## Testing Philosophy

Podquilt's regression suite is intentionally fixture-driven. No automated test reaches out to live podcast feeds, time-sensitive behavior is exercised with a frozen clock, and end-to-end golden tests assert the final rendered RSS output. Warnings, notices, and deprecations are treated as failures so PHP upgrades do not quietly erode behavior over time.

That means you can refactor aggressively, including around XML handling and fetch orchestration, without having to guess whether the emitted feed still matches the contract your listeners rely on.

## CI

GitHub Actions verifies the project on PHP 8.5 by running:

- `composer validate --strict`
- `composer check`
- `composer audit`

## Operational Notes

Podquilt is at the mercy of upstream publishers. Concurrent fetching helps a lot, but it does not make a dead or very slow feed disappear. If one source fails, Podquilt continues processing the others and logs the failure.

URL validation is handled through PHP 8.5's URI support rather than ad hoc filtering, and replay scheduling follows the rules described in the configuration guide.

## License

Podquilt is released under the [MIT License](LICENSE).
