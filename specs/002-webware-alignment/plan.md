# Implementation Plan: Webware-Tools Alignment

**Branch**: `002-webware-alignment` | **Date**: 2026-04-19 | **Spec**: `specs/002-webware-alignment/spec.md`

**Input**: Feature specification from `/specs/002-webware-alignment/spec.md`

## Summary

Close the alignment gap on `webware/asciidoc-php-multi-repo`, a package that was scaffolded ad hoc
and never derived from `webinertia/repo-template`. The repository gains the full alignment layer:
`webware-ci.json` with package-specific values, a Mago toolchain that extends the shared
`webware/webware-tools` configuration (replacing PHPStan level 10), PHPUnit 13 strict
configuration that keeps this package's two suites, Infection/Codecov/Renovate/PHPBench config
files, `.gitattributes`, `.gitignore`, `mago.bat`, `.devcontainer/devcontainer.json`,
`.github/copilot-instructions.md`, empty lint and analysis baselines, and the standard README badge
block.

The container stack (`Dockerfile`, `compose.yml`, `.dockerignore`) and the `webware/asciidoc-php`
Packagist move are already delivered by `tyrsson-container-stack`, the branch this feature stacks
on. This plan verifies those artifacts against the canonical copies instead of re-creating them.

## Technical Context

**Language/Version**: PHP `~8.4.0 || ~8.5.0` (unchanged; `config.platform.php` stays `8.4.99`)

**Primary Dependencies**: PHPUnit `^13.0.0` (locked 13.4.5), `webware/asciidoc-php` `^0.1`
(Packagist); adding `webware/webware-tools` `^1.0.0-rc.8` (dev, provides the shared `mago.toml`),
`infection/infection` `^0.35.0`, `phpbench/phpbench` `^1.7`,
`roave/backward-compatibility-check` `^8.21.0`; removing `phpstan/phpstan`

**Storage**: N/A. The required workflow's `db_*` keys are omitted because this package persists
nothing.

**Testing**: PHPUnit 13.4 (`unit test` suite over `tests/Unit`, `contract test` suite over
`tests/Contract`), Infection mutation testing with Mago as the static analysis tool, Mago
`format`/`lint`/`analyze`/`guard` as the static analysis gates

**Target Platform**: Linux CI runners (GitHub Actions, required workflow owned by
`webinertia/.github`) plus the containerized development environment delivered by
`tyrsson-container-stack`

**Project Type**: PHP library with a CLI entry point (`bin/asciidoc-php-multi-repo`)

**Performance Goals**: N/A

**Constraints**: CI matrix `["8.4", "8.5"]`; `min_msi` and `min_covered_msi` = 95; the Mago version
has exactly one home, the `version =` line in `webware/webware-tools/mago.toml`

**Scale/Scope**: Tooling alignment only. No change to `src/` behaviour, no new runtime feature, no
change to `specs/001-multi-repo-docs-site/tasks.md`, no change to `webware/asciidoc-php`

## Constitution Check

- **I. Webware-Tools CI Alignment**: `webware-ci.json`, required composer scripts, committed
  `composer.lock`, `mago.toml` extends the vendor config: satisfied by Phases 1, 3 and 5.
- **II. PHPUnit 13 Strict Mode**: `requireCoverageMetadata` plus the existing
  `failOnNotice`/`failOnWarning`/`failOnRisky` flags survive in `phpunit.xml.dist`, and
  `.github/copilot-instructions.md` states the mock-versus-stub rules (Phases 2, 5).
- **III. Code Quality Gates**: Mago clean with approved-only baselines, Infection MSI 95
  (Phases 3, 5).
- **IV. PHP Compatibility**: `require.php` is unchanged and carries no `8.6.0-dev`; platform stays
  `8.4.99` (Phase 1).
- **V. Naming**: the only namespace change is the guard remediation in Phase 3, which removes a
  `Command` segment collision rather than introducing repetition.

No principle is violated and no principle needs a waiver.

## Project Structure

### Documentation (this feature)

```text
specs/002-webware-alignment/
├── spec.md              # Feature spec (Package Parameters filled in)
├── plan.md              # This file
└── tasks.md             # Task list
```

### Repository artifacts this feature produces

Copied from `vendor/webware/webware-tools/presets/webware-alignment/artifacts/` (or from the local
preset clone before `composer install` makes `vendor/` available) and parameterized per the spec's
Package Parameters.

```text
webware-ci.json                            # required-workflow config, values only
mago.toml                                  # extends vendor/webware/webware-tools/mago.toml
lint-baseline.toml                         # generated empty, then maintained
analysis-baseline.toml                     # generated empty, then maintained
mago.bat                                   # Windows shim for Infection, copy verbatim
.gitattributes                             # merged with the existing entry
.gitignore                                 # canonical artifact, copy verbatim
phpunit.xml.dist                           # strict config, two suites preserved
infection.json5.dist                       # staticAnalysisTool = mago
codecov.yml                                # copy of the artifact
renovate.json                              # local>webinertia/.github:renovate-config
phpbench.json.dist                         # runner config only
.devcontainer/
└── devcontainer.json                      # thin VS Code wrapper over compose.yml
.github/
└── copilot-instructions.md                # PHPUnit 13 mock/stub and coverage rules
README.md                                  # standard badge block
composer.json                              # scripts + require-dev aligned
composer.lock                              # regenerated and committed
phpstan.neon                               # removed after the Mago gates are clean
```

Delivered by `tyrsson-container-stack` and verified here, not re-created: `Dockerfile`,
`compose.yml`, `.dockerignore`.

## Reference Mechanics (required workflow contract)

`webinertia/.github` owns `.github/workflows/org-required-ci.yml`; the organization ruleset binds it
to every repository, and that config repository's default branch is the ref it runs at. GitHub
invokes it directly, so a repository cannot pass `with:` inputs: per-package variation arrives as
`webware-ci.json` in the repository root.

- **`webware-ci.json` keys**: `php_versions` (JSON array), `run_integration`,
  `integration_php_version`, `composer_options`, `db_image`, `db_env_json`, `db_port`,
  `db_health_cmd`, `db_health_retries`, `db_health_interval_seconds`, `enable_codecov`,
  `enable_infection`, `coverage_php_version`, `min_msi`, `min_covered_msi`, `test_env_json`.
- **Secrets**: `CODECOV_TOKEN` and `INFECTION_DASHBOARD_API_KEY` are read by the workflow from
  repository or organization secrets; nothing is forwarded per package.
- **Jobs**:
  1. **config**: fails when `webware-ci.json` is absent from the repository root, then publishes
     every key as a job output. Each key has a default, so a misspelled key does not fail the build,
     it silently takes the default. The job also rejects a duplicated Mago version pin; that check
     is skipped in `webinertia/webware-tools` itself, which owns the pin.
  2. **mago**: matrix over `php_versions`; installs Mago at the version resolved from the centre's
     `mago.toml` pin, then runs `mago format --check`, `mago lint`, `mago analyze`, `mago guard`.
  3. **test**: matrix `php_versions` times `[lowest, locked, latest]`; `composer test` on
     non-canonical legs, `composer test-coverage` on the canonical leg (`coverage_php_version` plus
     locked, pcov); `composer test-integration` only when `run_integration`; uploads `clover.xml`.
  4. **codecov**: report-only, `fail_ci_if_error: false`.
  5. **mutation-test**: PHP `coverage_php_version` with pcov, Mago at the resolved pin,
     `composer mutation-test -- --min-msi=... --min-covered-msi=... --logger-github`; Infection
     invokes Mago through `staticAnalysisTool`.

Consumer obligations derived from the contract, all satisfied by this plan:

- `webware-ci.json` exists in the repository root (Phase 5).
- No Mago version literal anywhere in the repository (Phases 3, 7).
- Required composer scripts exist (Phase 1).
- `phpunit.xml.dist` defines the suites the workflow names. This package names them `unit test` and
  `contract test`; `test` targets `unit test` and `test-integration` targets `contract test`, which
  keeps `run_integration` meaningful for this repository (see Deviation D-2).
- `composer.lock` is committed (Phase 1).
- `mago.toml` is present (Phase 3).
- Every configured suite contains at least one test; the two suites already do.

## Package Parameters

| Parameter | Value | Source |
|---|---|---|
| `php_versions` | `["8.4", "8.5"]` | `require.php` `~8.4.0 || ~8.5.0` |
| `run_integration` | `false` | No integration suite; `tests/Contract` is a contract suite, not a network integration suite |
| `integration_php_version` | `""` | Integration disabled |
| `enable_codecov` | `true` | Coverage is produced by pcov in the container and on CI |
| `enable_infection` | `true` | Infection config ships with this feature |
| `coverage_php_version` | `"8.5"` | Highest supported minor, matches the container image |
| `min_msi` | `"95"` | Ecosystem standard |
| `min_covered_msi` | `"95"` | Ecosystem standard |

## Deviations from the canonical artifacts

Each deviation is required by this package's actual layout or by the constitution. The PR body
repeats this list.

- **D-1 `mago.toml` source paths.** The artifact ships `paths = ["src", "test"]`. This package's
  tests live in `tests/`, so the list becomes `["src", "tests"]`. `php-version` stays `"8.4.1"` to
  match `config.platform.php` `8.4.99`; `extends`, both baselines and `includes = ["vendor"]` are
  unchanged.
- **D-2 `phpunit.xml.dist` suites.** The artifact assumes a single `unit test` suite over
  `test/unit` and an `integration test` suite over `test/integration`. This package has `unit test`
  over `tests/Unit` and `contract test` over `tests/Contract`, and it sets `failOnRisky="true"` in
  addition to the artifact's `failOnNotice`/`failOnWarning`/`failOnDeprecation`. Both suites and all
  four strict flags are preserved; `requireCoverageMetadata="true"` and the relative schema pointer
  `vendor/phpunit/phpunit/phpunit.xsd` are adopted from the artifact.
- **D-3 `.gitattributes` export-ignore paths.** The artifact carries `/test/ export-ignore`. This
  package's directory is `tests/`, so the entry becomes `/tests/ export-ignore`. The existing
  `bin/asciidoc-php-multi-repo text eol=lf` entry is preserved in the merged file.
- **D-4 README badges.** The artifact opens with Packagist PHP-version and latest-version badges.
  `webware/asciidoc-php-multi-repo` is not published on Packagist (the package metadata endpoint
  returns 404), so those two badges would render as errors. The block keeps License, Required CI,
  codecov and Mutation testing, all with `DEFAULT_BRANCH` = `1.0.x`, and omits the two Packagist
  badges until the package is published.
- **D-5 Container artifacts.** `Dockerfile`, `compose.yml` and `.dockerignore` are owned by
  `tyrsson-container-stack`. This feature verifies that they exist, that the `tooling` service is
  the single source of truth, and that no Mago version literal appears in them; it does not edit
  them.
- **D-6 Guard remediation for the CLI command namespace.** `mago guard` cannot be baselined. The
  shared ruleset reserves `{App,Webware}\**\Command\*` for message-bus commands (final, named
  `*Command`, implementing `Webware\MessageBus\Command\NamedCommandInterface`) and requires
  interfaces to be named `*Interface`. This package's CLI subcommands are neither bus commands nor
  Symfony console commands, so satisfying either bucket would add a dependency this library must not
  carry. The namespace moves from `Webware\AsciidocPhpMultiRepo\Cli\Command` to
  `Webware\AsciidocPhpMultiRepo\Cli\Commands`, and `interface Command` is renamed to
  `interface CommandInterface`. No class is renamed, no behaviour changes, and `contracts/cli.md`
  is untouched because it documents flags, commands and exit codes, not class names.

## Implementation Phases

### Phase 1: composer.json and composer.lock

- Keep `require` exactly as it stands, including `webware/asciidoc-php: ^0.1` and the absence of a
  `repositories` entry. `require.php` stays `~8.4.0 || ~8.5.0`.
- `require-dev`: add `webware/webware-tools` `^1.0.0-rc.8`, `infection/infection` `^0.35.0`,
  `phpbench/phpbench` `^1.7`, `roave/backward-compatibility-check` `^8.21.0`,
  `roave/security-advisories` `dev-master`; remove `phpstan/phpstan`.
- `config.platform.php` stays `8.4.99`. Add `allow-plugins` for `infection/extension-installer`.
- `scripts`:
  - `test`: `phpunit --no-coverage --colors=always --testsuite "unit test"`
  - `test-coverage`: `phpunit --colors=always --coverage-clover clover.xml --coverage-text`
  - `test-integration`: `phpunit --no-coverage --colors=always --testsuite "contract test"`
  - `mutation-test`: `infection`
  - `sa`: `mago lint && mago analyze && mago guard` (the local static analysis gate)
  - `check-all`: `@sa && @test` (kept; `composer check-all` must keep working)
- Regenerate and commit `composer.lock` inside the container so the platform matches.
- PHPStan removal is deferred to Phase 4, after the Mago gates are clean.

### Phase 2: phpunit.xml.dist

- Adopt `bootstrap="vendor/autoload.php"`, `colors="true"`, `cacheDirectory=".phpunit.cache"`,
  `requireCoverageMetadata="true"` and the relative schema pointer
  `vendor/phpunit/phpunit/phpunit.xsd`. The relative path is deliberate: Infection validates the
  config against that schema before running a mutant, so an `https://schema.phpunit.de/...` URL
  makes the mutation job fail whenever the host is unreachable and silently pins a schema series
  that drifts from the installed PHPUnit.
- Keep suites `unit test` over `tests/Unit` and `contract test` over `tests/Contract`.
- Keep `failOnNotice`, `failOnWarning`, `failOnRisky`; add `failOnDeprecation` from the artifact.
- `<source restrictNotices="true" ignoreIndirectDeprecations="true">` including `src`.
- Adding `requireCoverageMetadata` means every test class needs `#[CoversClass]` or
  `#[CoversMethod]`; Phase 4 adds the attributes and Phase 5 documents the rule.

### Phase 3: Mago tooling

- `mago.toml` (new): `extends = "vendor/webware/webware-tools/mago.toml"`, `php-version =
  "8.4.1"`, `[linter] baseline = "lint-baseline.toml"`, `[analyzer] baseline =
  "analysis-baseline.toml"`, `[source] paths = ["src", "tests"]`, `includes = ["vendor"]`. No
  local `version =`, no schema URL.
- `lint-baseline.toml` and `analysis-baseline.toml`: generated by Mago, empty at the start. Only
  maintainer-approved suppressions are ever added; nothing is added by this feature.
- `mago.bat`: copy verbatim. It is a tracked artifact, not an ignored one.
- Fix pass, in this order: `mago format` (plain `--fix`, never `--potentially-unsafe` or
  `--unsafe`), then `mago lint`, `mago analyze`, `mago guard`.
  - Guard remediation is D-6: move `Cli\Command` to `Cli\Commands`, rename `interface Command` to
    `interface CommandInterface`, update every reference in `src/`, `tests/` and
    `bin/asciidoc-php-multi-repo`.
  - Lint and analyze findings in `src/` and `tests/` are fixed rather than suppressed. Whatever
    cannot be fixed without changing documented behaviour is baselined, and the baseline is called
    out in the PR body.
- `contracts/cli.md` is edited in the same commit as any change that adds or alters a flag, command
  or exit code, because `tests/Contract/ContractDocumentationTest.php` fails otherwise. This feature
  does not intend to change the CLI contract; the rename in D-6 is class-level only.

### Phase 4: Legacy static analysis removal

Only after all four Mago gates are clean:

- Delete `phpstan.neon`.
- Drop the last `phpstan/phpstan` reference from `composer.json` (the `sa` script is already
  rewritten in Phase 1) and refresh the lock.
- Confirm `composer test`, `composer sa` and `composer check-all` all run green under the new
  implementation.

### Phase 5: Required-workflow config, agent instructions, remaining artifacts

- `webware-ci.json`: the artifact with placeholders replaced by the Package Parameters table above.
  `php_versions` is a JSON array, `run_integration`/`enable_codecov`/`enable_infection` are unquoted
  booleans, `integration_php_version` is the empty string, and `min_msi`/`min_covered_msi` are the
  quoted strings `"95"`. No `db_*` keys, because the package persists nothing. No workflow file is
  created: the organization ruleset binds the required workflow, so this file is the only
  per-package CI surface, and no `ci-target` property is added by this feature.
- `.github/copilot-instructions.md`: the artifact with `PACKAGE_TITLE` replaced by this package's
  title, stating the PHPUnit 13 mock-versus-stub rules and the coverage-metadata rule.
- `.gitignore`: the artifact verbatim. It deliberately does not ignore `mago.bat`, and it keeps
  `.specify/` and `specs/` tracked while ignoring `/.specify/extensions/.cache/`.
- `.gitattributes`: the artifact merged with the existing
  `bin/asciidoc-php-multi-repo text eol=lf` line, with `/test/` corrected to `/tests/` (D-3).
- `infection.json5.dist`, `codecov.yml`, `renovate.json`, `phpbench.json.dist`: copied, with
  `source.directories = ["src"]` and `staticAnalysisTool = "mago"` confirmed in the Infection
  config.
- `.devcontainer/devcontainer.json`: copied. It wraps `../compose.yml` and the `tooling` service
  delivered by `tyrsson-container-stack`.

### Phase 6: README badges

- Insert the badge block from `readme-badges.md` with `ORG` = `webinertia`, `REPO` =
  `asciidoc-php-multi-repo`, `DEFAULT_BRANCH` = `1.0.x`, minus the two Packagist badges (D-4).
  Nothing is advertised that does not exist: the Required CI badge uses GitHub's
  `actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml` route, the
  codecov badge carries no `?branch=` parameter, and the Stryker badge embeds `1.0.x` in both the
  badge URL and the dashboard link.

### Phase 7: Container verification (every gate, no host PHP)

- `docker compose up -d`, then `docker compose exec tooling composer install`.
- `docker compose exec tooling composer test`: report the test count and assertion count.
- `docker compose exec tooling mago format --check`, `mago lint`, `mago analyze`, `mago guard`:
  report each exit status and finding count.
- `docker compose exec tooling composer sa` and `composer check-all`: report exit status.
- Grep the repository for a Mago version literal outside `webware-tools/mago.toml`: none may exist.
- Confirm LF endings with `git ls-files --eol`, confirm `mago.bat` is tracked and not ignored, and
  confirm no em dash (U+2014) or en dash (U+2013) appears in any added line.

## Complexity Tracking

No constitution violations.
