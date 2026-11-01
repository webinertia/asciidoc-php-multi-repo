# Feature Specification: Webware-Tools Alignment

**Feature Branch**: `002-webware-alignment`

**Created**: 2026-10-31

**Status**: Draft

**Input**: User description: "Align webware/asciidoc-php-multi-repo's CI/CD pipeline and dev
tooling with the required workflow"

## Purpose

Every Webware package runs the same CI/CD pipeline and dev tooling, owned centrally by
`webinertia/.github` (the workflow) and `webware/webware-tools` (the tool config), and varied per
package through `webware-ci.json`. This spec defines the
consumer-side contract: which artifacts a package must provide, what shape they take, and which
values are package-specific parameters.

This package was scaffolded ad hoc and never came from `webinertia/repo-template`. It therefore
carries none of the alignment layer: no `webware-ci.json`, no Mago configuration or baselines, no
Infection/Codecov/Renovate/PHPBench config, no `.devcontainer/`, and PHPStan level 10 as its only
static analysis gate. This feature closes that gap. The container stack (`Dockerfile`,
`compose.yml`, `.dockerignore`) arrives from the stacked container branch and is not re-created
here.

## Scope

**In scope:** CI/CD pipeline, tooling configs, composer metadata, baseline files, migration of
static analysis from PHPStan to Mago, the minimum test scaffolding required for a green pipeline,
`.devcontainer/devcontainer.json`, and README badges.

**Out of scope (explicit):**

- Re-creating `Dockerfile`, `compose.yml`, `.dockerignore`, or the `webware/asciidoc-php`
  dependency entry. The stacked container branch owns them.
- Any change to the `webinertia/asciidoc-php` package. Constitution principle I forbids it.
- Full test suite coverage; only the existing suites are required to stay green.
- A per-package wrapper workflow. The organization ruleset binds the required workflow.
- Renumbering, reordering or rewording any task in `specs/001-multi-repo-docs-site/tasks.md`.

## Package Parameters

Filled from this package's `composer.json` and the new `mago.toml`.

| Parameter | webware/asciidoc-php-multi-repo value |
|---|---|
| `php_versions` | `["8.4", "8.5"]` |
| `require.php` | `~8.4.1 \|\| ~8.5.0` |
| `config.platform.php` | `8.4.99` |
| `run_integration` | `false` (no true integration tests yet, see the suite mapping note) |
| `integration_php_version` | `""` (empty; `run_integration` is false) |
| `enable_codecov` | `true` |
| `enable_infection` | `true` |
| `coverage_php_version` | `"8.5"` |
| `min_msi` / `min_covered_msi` | `95` / `95` |
| DB container (`db_image`) | omitted; the package needs no database |
| Integration container | omitted |
| Tooling `PHP_VERSION` (Docker) | `8.5.12` (latest 8.5 patch; keep in sync with `require.php`) |
| Tooling Mago version (Docker) | derived from the central `mago.toml` pin via `composer.lock` (no literal to maintain) |
| Test autoload namespaces | `WebwareTest\AsciidocPhpMultiRepo\` -> `test/unit/`; `WebwareTestIntegration\AsciidocPhpMultiRepo\` -> `test/integration/` |
| PHPUnit suites | `unit test` -> `test/unit`; `integration test` -> `test/integration` |
| Mago source paths | `["src", "test", "bin/asciidoc-php-multi-repo"]` |

## User Scenarios & Testing

### User Story 1 - Maintainer opens a PR and gets a full pipeline (Priority: P1)

A maintainer opens a pull request against `1.0.x`. The required workflow runs Mago checks, the
test matrix (lowest/locked/latest dependency strategies), Codecov upload, and Infection mutation
testing.

**Why this priority**: The pipeline is the deliverable; nothing else in this spec has value
without it.

**Independent Test**: Open a PR touching `src/`; all CI jobs are scheduled and pass on a clean
change.

**Acceptance Scenarios**:

1. **Given** a PR against `1.0.x`, **When** pushed, **Then** Mago, test, codecov and mutation-test
   jobs all run.
2. **Given** a PR with a Mago lint finding, **When** pushed, **Then** the Mago job fails and
   reports the finding.
3. **Given** a change that empties a test suite, **When** pushed, **Then** the test job fails
   (PHPUnit errors on zero executed tests).

### User Story 2 - Tooling updates propagate with a version bump (Priority: P2)

When `webware/webware-tools` cuts a release, consumers move their lock entry to it and regenerate
baselines, without rewriting per-package config.

**Independent Test**: Move the `webware/webware-tools` lock entry; the pipeline stays green after
a `mago` fix pass.

**Acceptance Scenarios**:

1. **Given** a new webware-tools version, **When** the lock entry moves, **Then** only
   `composer.lock` and possibly baselines change.

### User Story 3 - Developer runs the gates without host PHP (Priority: P2)

A developer on Windows with only Docker installed starts the tooling container and runs the test
suite and all four Mago gates.

**Independent Test**: `docker compose run --rm tooling mago guard` succeeds with no native PHP on
the host.

**Acceptance Scenarios**:

1. **Given** a fresh clone and Docker, **When** `docker compose run --rm tooling mago --version`
   runs, **Then** the `tooling` image builds and starts and the container exits with the version.
2. **Given** a running container, **When** `mago format --check`, `mago lint`, `mago analyze` and
   `mago guard` run, **Then** each reports zero unbaselined issues.

### Edge Cases

- The former `contract test` suite is mapped onto `unit test`, not `integration test`. Both files
  read a committed artifact and compare it to code (`contracts/cli.md` against the registered
  commands, options and exit codes; the published schemas against `SchemaValidator`). No composed
  pipeline and no external boundary is exercised, so they are unit-level consistency checks. In
  this ecosystem `integration` means composed, wiring or rendering behaviour.
- `test/integration/` is therefore empty. The preset requires the suite to be named exactly
  `integration test`, so the suite stays defined and the directory is kept with a `.gitkeep`.
  `run_integration` stays `false` because the package has no true integration tests yet; Group 2
  and Group 3 populate the suite as composed behaviour lands.
- The owner directed that one integration test be written for the coupling between this package and
  the `webware/asciidoc-php` converter, and that an empty suite failing is acceptable until then.
  That test is blocked. The dependency is required in `composer.json` but referenced nowhere in
  `src`, `bin` or `test`, and the command that would drive rendering is a `StubCommand`. Group 1 has
  no seam where a resolved document reaches the converter, so no test is written and no seam or
  placeholder is invented.
- An empty suite is a PHPUnit failure: `composer test-integration` exits 1 with `No tests
  executed!`, and `composer test-all` fails on that leg. This is the truthful result of having no
  integration tests, not a misconfiguration. No filler test is added to silence it, and
  `--do-not-fail-on-empty-test-suite` is not applied. The required workflow never runs the leg while
  `run_integration` is `false`, so CI is unaffected.
- `composer sa` and `composer check-all` are removed. The preset plan template removes the legacy
  `check-all`/`cs-check`/`cs-fix`/`sa` scripts and invokes the four `mago` commands directly, so no
  convenience wrapper is shipped.
- Zero tests: PHPUnit 13 errors, and Infection cannot score an empty suite.
- A guard finding is never baselined. Guard findings are fixed in source.

## Requirements

### Functional Requirements

- **FR-001**: Repository MUST provide `webware-ci.json` in the repository root carrying the
  package's CI values, and MUST NOT carry a wrapper workflow. The file MUST carry no Mago version
  literal.
- **FR-002**: `composer.json` MUST keep the existing `test` script unchanged, MUST drop the legacy
  `sa` and `check-all` scripts, and MUST add `test-coverage`, `test-integration`, `mutation-test`
  and `test-all` scripts that the preset plan template defines. `test-all` MUST be declared as an
  array of `@script` references rather than the template's `&&`-joined string, because Composer
  2.11.1 does not chain `@script` references inside a single string and renders the trailing
  `&& @test-integration` as arguments to the first script.
- **FR-003**: `phpunit.xml.dist` MUST use the PHPUnit 13.1 schema, keep the strict flags
  (`failOnNotice`, `failOnWarning`, `failOnRisky`), and keep both suites (`unit test` ->
  `test/unit`, `integration test` -> `test/integration`).
- **FR-004**: `mago.toml` MUST extend `vendor/webware/webware-tools/mago.toml` and define only
  `php-version`, baseline paths, and source paths matching this package's layout (`src`, `test`,
  `bin/asciidoc-php-multi-repo`). General settings and general guard rules MUST NOT be duplicated
  locally.
- **FR-005**: `lint-baseline.toml` and `analysis-baseline.toml` MUST be generated by Mago with
  `--generate-baseline`, MUST NOT be hand-written or hand-edited, and MUST NOT carry an entry for a
  finding the safe fixer could have cleared. `mago guard` findings are never baselined.
- **FR-006**: `infection.json5.dist` MUST configure `source.directories = ["src"]` and
  `staticAnalysisTool: "mago"`.
- **FR-007**: `codecov.yml` MUST be copied from the preset's `artifacts/codecov.yml`.
- **FR-008**: `renovate.json` MUST extend `local>webinertia/.github:renovate-config`.
- **FR-009**: `phpbench.json.dist` MUST exist with runner config; a `benchmarks/` directory is not
  required.
- **FR-010**: `composer.lock` MUST be committed.
- **FR-011**: `.github/copilot-instructions.md` MUST carry the fleet working agreements and the
  PHPUnit 13 mock-vs-stub rules.
- **FR-012**: Each test suite MUST contain at least one test.
- **FR-013**: `README.md` MUST carry the standard badge block for the badges whose targets exist:
  License, Required CI, codecov and Mutation testing. Packagist badges MUST be omitted until the
  package is published.
- **FR-014**: `.gitignore` MUST be the canonical artifact. `.specify/` and `specs/` stay tracked.
- **FR-015**: Repository MUST remove `phpstan/phpstan` from `require-dev`, delete `phpstan.neon`,
  and replace the PHPStan-based `sa` script with Mago. Removal happens only after the Mago gates
  are clean.
- **FR-016**: Repository MUST provide a containerized development environment. `Dockerfile`,
  `compose.yml` and `.dockerignore` arrive from the stacked container branch; this feature owns
  `.devcontainer/devcontainer.json`.
- **FR-017**: `.devcontainer/devcontainer.json` MUST reference `compose.yml` via
  `dockerComposeFile` + `service: tooling`.
- **FR-018**: All local tooling MUST be runnable inside the container via
  `docker compose exec tooling ...`, with no native PHP toolchain required on the host.
- **FR-019**: The package needs no database, so no `db_*` keys and no `mysql` service.

### Key Entities

- **`webware-ci.json`**: the only per-package CI surface - values, no workflow ref.
- **Required workflow**: owned by `webinertia/.github`; owns job definitions.
- **Baselines**: per-package TOML files holding approved Mago suppressions.

## Success Criteria

### Measurable Outcomes

- **SC-001**: All CI jobs green on `1.0.x`: Mago (all versions), test matrix, codecov,
  mutation-test.
- **SC-002**: `mago format --check`, `mago lint`, `mago analyze`, `mago guard` report zero
  unbaselined issues.
- **SC-003**: Infection MSI and covered MSI at or above 95.
- **SC-004**: Codecov receives coverage upload from exactly one matrix leg (`8.5` + locked).
- **SC-005**: On a Windows machine with only Docker installed, `docker compose up -d` and
  `docker compose exec tooling composer test` succeed, and the repository opens in a VS Code Dev
  Container backed by `compose.yml`.

## Assumptions

- The required workflow keeps its documented `webware-ci.json` keys unless a deliberate change
  records otherwise.
- The contract suite is not an integration suite, so it lives in `unit test`, and `run_integration`
  is `false` until a genuine composed test exists.
- Test scaffolding (not full test coverage) is sufficient for alignment scope.