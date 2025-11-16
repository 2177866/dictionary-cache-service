# Contributing

Thanks for helping improve Dictionary Cache Service! Follow this guide to set up your environment and submit high‑quality contributions.

## Requirements

- PHP **7.4+** (CI currently runs 8.1–8.3).
- Composer.
- `ext-redis` only if you want to run integration tests locally.
- Any Redis-compatible database (Redis, KeyDB, Valkey, Dragonfly, Ardb) — easiest way is via Docker.

## Getting Started

```bash
git clone https://github.com/alyakin/dictionary-cache-service.git
cd dictionary-cache-service
composer install
```

### Commands

- `composer lint` – Laravel Pint (same as `pint --test` in CI).
- `composer stan` – PHPStan level 9.
- `composer test` – PHPUnit (unit + integration).
- `composer check` – runs lint → PHPStan → PHPUnit sequentially.

Integration tests read `REDIS_HOST`/`REDIS_PORT` (`127.0.0.1:6379` by default). Point them to any Redis-compatible server to reproduce CI conditions.

## Style & Tests

- Follow PSR-12 and the rules in `.pint.json`.
- Document the public API with PHPDoc when types are not obvious.
- Add or update tests for every behavioral change.
- Keep pull requests focused and avoid unrelated refactors.

## Submitting Changes

1. Run `composer check` locally.
2. Update README/CHANGELOG when behavior or APIs change.
3. Open a PR using the provided template and describe your testing.
4. Be ready to revise the PR after review.

## Reporting Issues / Ideas

Use the GitHub issue templates (Bug report / Feature request). Provide PHP version, Redis driver, stack traces, and exact reproduction steps whenever possible.

Thank you for contributing! If you have questions, open an issue or start a discussion in your PR.
