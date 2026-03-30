# Podquilt

Podquilt merges multiple podcast feeds into one personal RSS feed. It is driven by a local `config.json`, preserves the original item XML where possible, and applies deterministic filtering, replay scheduling, and file-backed synthetic items before publishing a single RSS document.

## Features

- Merge any number of remote podcast feeds into one RSS feed
- Limit items per source and cap retention by age
- Filter items by arbitrary RSS node names, including namespaced nodes such as `itunes:episodeType`
- Replay archived shows on a cron schedule without changing the source feed
- Add one-off file-backed episodes directly from `config.json`
- Log invalid feeds, replay configuration errors, and request timing

## Requirements

- PHP 8.5
- Composer 2
- PHP extensions:
  - `dom`
  - `hash`
  - `json`
  - `libxml`
- Docker Desktop with `docker compose` is optional but fully supported

## Quick Start

1. Copy `config.json.example` to `config.json`.
2. Edit `config.json` with your own feed sources.
3. Choose either the Docker or system-PHP workflow below.

### Docker Workflow

Install dependencies:

```bash
docker compose run --rm app composer install
```

Start the development server:

```bash
docker compose up
```

Podquilt will be available at [http://127.0.0.1:8000](http://127.0.0.1:8000).

Run the full verification suite:

```bash
docker compose run --rm app composer check
docker compose run --rm app composer audit
```

### System PHP Workflow

Install dependencies:

```bash
composer install
```

Start the development server:

```bash
composer serve
```

Run the verification suite:

```bash
composer check
composer audit
```

## Configuration

The public configuration schema remains file-based and lives in `config.json`.

### `feeds`

Remote podcast feeds to merge.

Supported keys:

- `url`
- `prepend`
- `item_limit`
- `item_max_age`
- `filter`
- `replay`
- `disabled`

### `files`

Synthetic RSS items backed by a direct media URL.

Supported keys:

- `url`
- `title`
- `pubDate`
- `description`
- `disabled`

### `channel`

RSS channel metadata for the merged output.

Supported keys:

- `title`
- `link`
- `description`

### `logs`

File-based logging configuration.

Supported keys:

- `enabled`
- `level`
- `path`

## Example Config

See [config.json.example](config.json.example) for a full example, including feed filtering, disabled sources, replay scheduling, and file-backed episodes.

## Development Commands

- `composer serve`: Run the PHP built-in server on port `8000`
- `composer test`: Run the PHPUnit regression suite
- `composer analyse`: Run PHPStan at the strict project level
- `composer format`: Apply PHP CS Fixer rules
- `composer check`: Run tests, static analysis, and dry-run formatting
- `composer audit`: Check dependencies for published security advisories

## Testing Strategy

The test suite is intentionally fixture-driven:

- No automated test hits a live podcast feed
- Time-sensitive behavior uses a frozen clock
- End-to-end golden tests assert the final rendered RSS output
- PHP warnings, notices, and deprecations fail the suite

## CI

GitHub Actions runs the following on PHP 8.5:

- `composer validate --strict`
- `composer install`
- `composer check`
- `composer audit`

## Operational Notes

- Feed aggregation speed depends on the configured remote sources. Slow first responses are often caused by upstream feeds, not local boot issues.
- Podquilt uses the PHP 8.5 URI extension to validate configured URLs before fetching them.
- Replay behavior intentionally preserves the historical Podquilt semantics so existing configs continue to work.

## License

Podquilt is released under the [MIT License](LICENSE).
