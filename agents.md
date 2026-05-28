# agents.md — search_elastic

## Repository Overview

Search Elastic is an ownCloud Server (OC10) app that provides Elasticsearch-based full-text search. It indexes file contents using Apache Tika and filters results by user access permissions.

- **Classification:** Classic (OC10)
- **Activity Status:** Active
- **License:** GPL-2.0
- **Language:** PHP

## Architecture & Key Paths

- `appinfo/` — ownCloud app metadata (info.xml, routes)
- `lib/` — PHP backend (Elasticsearch client, indexing, search providers)
- `js/` — Frontend JavaScript
- `css/` — Stylesheets
- `templates/` — PHP templates
- `tests/` — PHPUnit and acceptance tests
- `Makefile` — Build orchestration
- `composer.json` — PHP dependency management
- `phpcs.xml` — Code style configuration
- `phpstan.neon` — Static analysis configuration
- `FEATURES.md` — Feature documentation
- `TESTING.md` — Testing instructions

## Development Conventions

- Standard ownCloud OC10 app structure
- Code style enforced by phpcs (`phpcs.xml`)
- Static analysis via PHPStan (`phpstan.neon`)
- SonarCloud integration for quality metrics

## Build & Test Commands

```bash
make all                    # Install all dependencies
make dist                   # Build distribution package
make clean                  # Clean build artifacts
make test-php-unit          # Run PHP unit tests
make test-php-style         # Check code style (phpcs)
make test-php-style-fix     # Auto-fix code style issues
make test-php-phpstan       # Run PHPStan static analysis
make test-php-phan          # Run Phan static analysis
make test-acceptance-api    # Run API acceptance tests
make test-acceptance-cli    # Run CLI acceptance tests
make test-acceptance-webui  # Run webUI acceptance tests
```

## Important Constraints

- **GPL-2.0 copyleft license:** This repository is currently GPL-2.0. The OSPO Apache 2.0 migration requires auditing all copyleft dependencies and contributor agreements before relicensing.
- **Elasticsearch dependency:** Requires a running Elasticsearch instance.
- **Apache Tika:** Content extraction relies on Tika for document parsing.
- **Encryption compatibility:** Compatible with master key encryption but not with user-individual keys.


## OSPO Policy Constraints

### GitHub Actions
- **Only** use actions owned by `owncloud`, created by GitHub (`actions/*`), verified on the GitHub Marketplace, or verified by the ownCloud Maintainers.
- Pin all actions to their full commit SHA (not tags): `uses: actions/checkout@<SHA> # vX.Y.Z`
- Never introduce actions from unverified third parties.

### Dependency Management
- Dependabot is configured for automated dependency updates.
- Review and merge Dependabot PRs as part of regular maintenance.
- Do not introduce new dependencies without discussion in an issue first.

### Git Workflow
- **Rebase policy**: Always rebase; never create merge commits. Use `git pull --rebase` and `git rebase` before pushing.
- **Signed commits**: All commits **must** be PGP/GPG signed (`git commit -S -s`).
- **DCO sign-off**: Every commit needs a `Signed-off-by` line (`git commit -s`).
- **Conventional Commits & Squash Merge**: Use the [Conventional Commits](https://www.conventionalcommits.org/) format where the repository enforces it. Many repos use squash merge, where the PR title becomes the commit message on the default branch — apply Conventional Commits format to PR titles as well. A reusable GitHub Actions workflow enforces this.

## Context for AI Agents

- This is an ownCloud Server (OC10) app, not an oCIS extension.
- The `lib/` directory contains the core indexing and search logic.
- Configuration is done via `occ` commands for the ownCloud CLI.
- The app uses background jobs (cron) for indexing operations.
- Test suite includes unit tests, acceptance tests (API, CLI, webUI), and static analysis.
