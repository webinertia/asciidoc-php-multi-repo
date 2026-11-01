# Implementation Plan: Webware-Tools Alignment

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: `specs/[###-feature-name]/spec.md`

**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

## Summary

Align the package's CI/CD pipeline and dev-tooling configuration with the organization's
required CI workflow. The package provides `webware-ci.json` with package-specific values,
aligned composer metadata, PHPUnit 13 strict configuration, Mago tooling
extending the shared config, Infection/Codecov/Renovate/PHPBench config files, and committed
`composer.lock`.

## Technical Context

**Language/Version**: PHP `~8.4.1 || ~8.5.0`

**Primary Dependencies**: PHPUnit `^13.3.0`, Infection `^0.35.0`, PHPBench `^1.7`,
roave/backward-compatibility-check `^8.21.0`, `webware/webware-tools` (dev, for `mago.toml`
extend)

**Storage**: [Package-specific: N/A for no-DB packages; the required workflow's DB steps are
skipped when `db_image` is omitted]

**Testing**: PHPUnit 13.1 (`unit test` and `integration test` suites), Infection mutation testing
with Mago as static analysis tool

**Target Platform**: Linux CI runners (GitHub Actions) + a containerized development environment
(Docker Compose / VS Code Dev Container), so developers can work inside the container identically
on Windows, WSL, Linux, and macOS.

**Project Type**: PHP library

**Performance Goals**: N/A

**Constraints**: CI matrix `["8.4", "8.5"]`; `config.platform.php` = `8.4.99`; `min_msi` and
`min_covered_msi` = 95

**Scale/Scope**: Tooling alignment only (test scaffolding, not full coverage)

## Constitution Check

- **I. Webware-Tools CI Alignment**: `webware-ci.json`, required composer scripts, committed
  `composer.lock`, `mago.toml` extends vendor config: satisfied by this plan (Phases 1, 2, 3).
- **II. PHPUnit 13 Strict Mode**: `requireCoverageMetadata` + mock/stub rules: enforced via
  `phpunit.xml.dist` and `.github/copilot-instructions.md` (Phases 2, 5).
- **III. Code Quality Gates**: Mago clean + approved-only baselines; Infection MSI thresholds
  (Phases 3, 4).
- **IV. PHP Compatibility**: no `8.6.0-dev` in `require.php`; platform `8.4.99` (Phase 1).
- **V. Naming**: no namespace repetition: no new classes introduced by this plan.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── spec.md              # Feature spec (package parameters filled in)
├── plan.md              # This file
└── tasks.md             # Task list
```

### Source Code (repository root): artifacts this feature produces

All root-level artifacts below are copied from
`vendor/webware/webware-tools/presets/webware-alignment/artifacts/` and parameterized per the
spec's Package Parameters.

```text
.github/
└── copilot-instructions.md                # PHPUnit 13 rules
webware-ci.json                            # required-workflow config (values only, no workflow ref)
Dockerfile                                 # PHP + Composer + Mago + Xdebug dev image
compose.yml                                # persistent `tooling` service (the source of truth)
.dockerignore                              # exclude vendor/, .git/, etc. from build context
.devcontainer/
└── devcontainer.json                      # thin VS Code wrapper around compose.yml
phpunit.xml.dist                           # strict PHPUnit 13.1 config
mago.toml                                  # extends vendor/webware/webware-tools/mago.toml
lint-baseline.toml                         # starts empty
analysis-baseline.toml                     # starts empty
infection.json5.dist                       # mago staticAnalysisTool
codecov.yml                                # copy of preset artifacts/codecov.yml
renovate.json                              # local>webinertia/.github:renovate-config
phpbench.json.dist                         # runner config only
composer.json                              # scripts + require-dev aligned
composer.lock                              # generated + committed
README.md                                  # standard badge set
test/
├── unit/                                  # WebwareTest\<Package>\
└── integration/                           # WebwareTestIntegration\<Package>\
```

## Reference Mechanics (required workflow contract)

`webinertia/.github` owns `.github/workflows/org-required-ci.yml`; the organization ruleset binds it
to every repository, and that config repository's default branch is the ref it runs at. GitHub
invokes it directly, so a repository cannot pass `with:` inputs - per-package variation arrives as
`webware-ci.json` in the repository root:

- **`webware-ci.json` keys**: `php_versions` (JSON array), `run_integration`,
  `integration_php_version`, `composer_options`, `db_image`, `db_env_json`, `db_port`,
  `db_health_cmd`, `db_health_retries`, `db_health_interval_seconds`, `enable_codecov`,
  `enable_infection`, `coverage_php_version`, `min_msi`, `min_covered_msi`, `test_env_json`.
- **Secrets**: `CODECOV_TOKEN` and `INFECTION_DASHBOARD_API_KEY` are read by the workflow from
  repository/org secrets; nothing is forwarded per package.
- **Jobs**:
  1. **config**: runs on every repository; fails when `webware-ci.json` is absent from the root,
     then publishes every key as a job output. Each key has a default (`php_versions`
     `["8.4", "8.5"]`, `min_msi` / `min_covered_msi` `"10"`, booleans `false`, the rest empty), so
     a misspelled key does not fail the build - it silently takes the default. The job also rejects
     a duplicated Mago version pin; that check is skipped in `webinertia/webware-tools` itself,
     which owns the pin.
  2. **mago**: needs `config`; matrix over `php_versions`; installs Mago at the version resolved
     from the centre's `mago.toml` pin, then runs `mago format --check`, `mago lint`,
     `mago analyze`, `mago guard` (each `success() || failure()`).
  3. **test**: needs `config`; matrix `php_versions` × `[lowest, locked, latest]`; optional DB
     container (skipped when `db_image` empty); `composer test` on non-canonical legs, `composer
     test-coverage` on the canonical leg (`coverage_php_version` + locked, pcov);
     `composer test-integration` when `run_integration`; uploads `clover.xml` artifact. Both the DB
     container and the integration suite are additionally gated on `run_integration`, and narrowed
     by `integration_php_version` when set - the integration suite is the expensive part of a leg,
     so running it on every PHP version is rarely worth the wall-clock time.
  4. **codecov**: needs `[config, test]`; `codecov/codecov-action@v5`, `files: clover.xml`,
     `fail_ci_if_error: false` (report-only).
  5. **mutation-test**: needs `[config, test]`; PHP `coverage_php_version` with pcov, Mago at the
     resolved pin; `composer mutation-test -- --min-msi=… --min-covered-msi=… --logger-github`;
     Infection invokes Mago via `staticAnalysisTool`.

Consumer obligations derived from the contract:

- `webware-ci.json` must exist in the repository root - the `config` job fails the build without
  it.
- No Mago version literal anywhere in the repository: the pin is inherited from
  `webware/webware-tools/mago.toml`, and the `config` job rejects a copy of it.
- Required composer scripts must exist.
- `phpunit.xml.dist` must define suites named `unit test` and `integration test`.
- `composer.lock` must be committed.
- `mago.toml` must be present.
- Pipeline is red until at least one test exists per suite (PHPUnit errors on zero tests;
  Infection cannot score an empty suite).

## Implementation Phases

### Phase 1: composer.json

- `require.php`: `~8.4.1 || ~8.5.0`. Do not add `8.6.0-dev`.
- `require-dev`: PHPUnit `^13.3.0`; add Infection `^0.35.0`, PHPBench `^1.7`,
  roave/backward-compatibility-check `^8.21.0`; keep existing package deps,
  `webware/webware-tools`, `roave/security-advisories`; remove `phpstan/phpstan`,
  `phpstan/phpstan-phpunit`, and `webware/coding-standard`.
- `config.platform.php`: `8.4.99`.
- `autoload-dev`: `WebwareTest\<Package>\` → `test/unit/`; add
  `WebwareTestIntegration\<Package>\` → `test/integration/`.
- `scripts`:
  - `test`: `phpunit --no-coverage --colors=always --testsuite "unit test"`
  - `test-coverage`: `phpunit --colors=always --coverage-clover clover.xml --coverage-text`
  - `test-integration`: `phpunit --no-coverage --colors=always --testsuite "integration test"`
  - `mutation-test`: `infection`
  - `test-all`: `@test && @test-integration && @mutation-test`
- No PHPStan package, no PHPStan script; remove legacy `check-all`/`cs-check`/`cs-fix`/`sa`
  scripts.
- Regenerate + commit `composer.lock`.

### Phase 2: phpunit.xml.dist (new)

- Schema pointer `vendor/phpunit/phpunit/phpunit.xsd`. The relative path is deliberate:
  Infection validates `phpunit.xml.dist` against this schema before running a single
  mutant, so an `https://schema.phpunit.de/...` URL makes the mutation job fail whenever
  that host is unreachable, and it silently pins a schema series that drifts away from the
  installed PHPUnit. This is the value `phpunit --generate-configuration` writes itself.
- `bootstrap="vendor/autoload.php"`, `colors="true"`, `cacheDirectory=".phpunit.cache"`.
- Strict flags: `requireCoverageMetadata="true"`, `failOnNotice="true"`,
  `failOnDeprecation="true"`, `failOnWarning="true"`.
- Testsuites: `unit test` → `test/unit`; `integration test` → `test/integration`.
- `<source restrictNotices="true" ignoreIndirectDeprecations="true">` including `src`.
- No bootstrap extensions, no env vars.
- Remove legacy tooling configs: `.php-cs-fixer.dist.php`, `.php-cs-fixer.php`,
  `.php-cs-fixer.cache`, `phpstan.neon.dist`, `phpstan-baseline.neon`, `stubs/`, `.laminas-ci.json`.

### Phase 3: Mago tooling

- `mago.toml` (new):
  - `extends = "vendor/webware/webware-tools/mago.toml"`
  - `php-version = "8.4.1"`
  - `[linter] baseline = "lint-baseline.toml"`; `[analyzer] baseline = "analysis-baseline.toml"`
  - `[source] paths = ["src", "test"]`, `includes = ["vendor"]`
- Remove the legacy `mago.json` (pre-alignment mago config).
- `lint-baseline.toml` + `analysis-baseline.toml`: start empty; only maintainer-approved
  suppressions added.
- Fix pass: `mago format`, then `mago lint` + `mago analyze` + `mago guard`; fix all `src/`
  findings; baseline approved remainder only.

### Phase 4: Infection / Codecov / Renovate / PHPBench configs

- `infection.json5.dist`: `source.directories = ["src"]`, `timeout = 10`, `threads = "max"`,
  logs `text: infection.log`, `summary: summary.log`, stryker badge regex `/^\d+\.\d+\.x$/`,
  `mutators: {"@default": true}`, `staticAnalysisTool: "mago"`.
- `codecov.yml`: copy from the preset's `artifacts/codecov.yml` (targets `auto`, threshold
  `0%`, comment layout `diff, flags, files`).
- `renovate.json`: `"extends": ["local>webinertia/.github:renovate-config"]`.
- `phpbench.json.dist`: `runner.path: benchmarks`, `*Bench.php`; config only, no
  `benchmarks/` directory, no CI job.

### Phase 5: Required-workflow config + agent instructions + test scaffolding

- `webware-ci.json`: copied from the preset's `artifacts/webware-ci.json` with the placeholders
  replaced by the spec's Package Parameters (`php_versions`, `run_integration`,
  `integration_php_version`, `enable_codecov`, `enable_infection`, `coverage_php_version`,
  `min_msi`, `min_covered_msi`). No workflow file is created: the organization ruleset binds the
  required workflow to the repository, so this file is the only per-package CI surface. Omit the
  `db_*` keys when the package needs no database.
- `.github/copilot-instructions.md`: PHPUnit 13 mock-vs-stub rules (`createStub()` for
  value-returning doubles, `createMock()` only with `expects()`) and
  `requireCoverageMetadata="true"` rules (`#[CoversClass]` / `#[CoversMethod]` per test class).
- `test/`: scaffolding with at least one test per suite so the pipeline is green (PHPUnit
  errors on zero executed tests; Infection cannot score an empty suite).
- `.gitignore`: add `/.specify/` and `/specs/` - spec-kit scaffolding is local dev tooling and
  is never pushed to the remote; `/.github/` is already ignored and covers agent skill dirs.

### Phase 6: README badges

- `README.md`: standard badge block from the preset's `artifacts/readme-badges.md` (PHP
  version, latest version, license, Required CI, codecov, Mutation testing). CI and
  codecov badge URLs carry no `?branch=` parameter, so they always point at the default branch.
  The Stryker mutation badge URL embeds the branch segment; update that segment in both the
  badge URL and the dashboard link whenever the default branch changes.

### Phase 7: Containerized development environment

- `Dockerfile` (new): copy from the preset's `artifacts/Dockerfile`. Base image
  `php:8.5.12-cli` (latest 8.5 patch, in sync with `require.php`); install Composer from the
  official `composer` image; download the Mago release asset for `${TARGETARCH}` (amd64 →
  `x86_64`, arm64 → `aarch64`) at the Mago version derived from the webware-tools `mago.toml`
  pin (`version =`, resolved through `composer.lock`) and install to `/usr/local/bin/mago`;
  install `intl`, `pcntl`, `zip`, and `pcov` extensions; install Xdebug (off by default,
  `XDEBUG_MODE=debug` to enable); `WORKDIR /app`.
- `compose.yml` (new): copy from the preset's `artifacts/compose.yml`. A single persistent,
  interactive `tooling` service (`sleep infinity`, `stdin_open`/`tty`) that builds the image (with
  `PHP_VERSION` build arg), bind-mounts the repository at `/app`, keeps
  `vendor/` and the Composer cache in named volumes, and exposes `host.docker.internal` for
  Xdebug. This is the single source of truth for the environment. For packages whose tests need
  MySQL, uncomment the opt-in `mysql` service (and the `depends_on` block on `tooling`); it
  mirrors the CI `db_image` / `db_env_json` Package Parameters and is reachable from `tooling` as
  host `mysql` on port 3306 (matching the PhpDb `mysql.local.php` convention). An opt-in
  `phpmyadmin` service provides a web UI (http://localhost:8080) over the database.
- `.dockerignore` (new): copy from the preset's `artifacts/.dockerignore` (exclude `vendor/`,
  `.git/`, `.github/`, `.devcontainer/`, `.specify/`, `specs/`, `.phpunit.cache`, `coverage/`,
  `*.log`).
- `.devcontainer/devcontainer.json` (new): copy from the preset's `artifacts/devcontainer.json`.
  A thin wrapper (`dockerComposeFile` → `compose.yml`, `service: tooling`, `workspaceFolder
  /app`) so VS Code users reuse the exact same container as plain Compose users - no one is
  orphaned.
- Verify: `docker compose up -d` then `docker compose exec tooling composer test` and the full
  `mago format --check && mago lint && mago analyze && mago guard` sequence run green with no
  native PHP toolchain on the host; `docker compose exec tooling php -m` lists `xdebug`; "Reopen
  in Container" resolves against `compose.yml`.
- All files committed with LF line endings (enforced by the package `.gitattributes`).

## Complexity Tracking

No constitution violations.
