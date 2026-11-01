# Tasks: Webware-Tools Alignment

**Input**: `specs/002-webware-alignment/spec.md`, `plan.md`

Package parameters are already applied throughout: PHP versions `8.4` and `8.5`, test roots
`tests/Unit` and `tests/Contract`, namespaces `Webware\AsciidocPhpMultiRepo\Test\` (unit and
contract). The MSI gates are 95 and are not a package parameter.

Branch base: `1.0.x`. The container work landed as PR #3 and `1.0.x` now carries `Dockerfile`,
`compose.yml` and `.dockerignore`, so T019 replaces the transitional `Dockerfile` with the
canonical artifact and T020/T021 are verification only. All four Mago gates and both test suites
run in the container; no host PHP is used.

## Phase 1: composer.json

- [x] T001 Verify `composer.json` `require.php` is `~8.4.0 || ~8.5.0` with no `8.6.0-dev`, and that
  `config.platform.php` is `8.4.99`. Leave `require` untouched otherwise, including
  `webware/asciidoc-php: ^0.1` and the absent `repositories` entry
- [x] T002 Align `require-dev`: keep `phpunit/phpunit: ^13.0.0`; add
  `webware/webware-tools: ^1.0.0-rc.8`, `infection/infection: ^0.35.0`,
  `phpbench/phpbench: ^1.7`, `roave/backward-compatibility-check: ^8.21.0`,
  `roave/security-advisories: dev-master`. Add `config.allow-plugins` for
  `infection/extension-installer`. Do not remove `phpstan/phpstan` yet (T010a)
- [x] T003 Verify `autoload-dev` maps `Webware\AsciidocPhpMultiRepo\Test\` to `tests/`. No
  `integration` namespace is added: this package has a contract suite, not an integration suite
- [x] T004 Define scripts `test` (both suites, unchanged), `test-coverage` (clover plus text),
  `test-integration` (testsuite `contract test`), `mutation-test` (`infection`), move `sa` off
  PHPStan to Mago (`mago format --check && mago lint && mago analyze && mago guard`) and keep
  `check-all` (`@sa` then `@test`) so it still means something real
- [x] T005 Run `composer update` inside the container and commit `composer.lock`

## Phase 2: phpunit.xml.dist

- [x] T006 Update `phpunit.xml.dist`: relative schema pointer
  `vendor/phpunit/phpunit/phpunit.xsd`, `bootstrap="vendor/autoload.php"`, `colors="true"`,
  `cacheDirectory=".phpunit.cache"`, `requireCoverageMetadata="true"`, and strict flags
  `failOnNotice`, `failOnDeprecation`, `failOnWarning`, `failOnRisky` all `true`. Preserve both
  suites: `unit test` over `tests/Unit` and `contract test` over `tests/Contract`. Keep
  `<source>` over `src` (Deviation D-2)
- [x] T007 Add `#[CoversClass]` (or `#[CoversMethod]`) to every test class so
  `requireCoverageMetadata` holds. Both suites already contain tests, so no scaffolding is needed

## Phase 3: Mago tooling

- [x] T008 Create `mago.toml` from the preset's `artifacts/mago.toml`:
  `extends = "vendor/webware/webware-tools/mago.toml"`, `php-version = "8.4.1"`, linter and
  analyzer baseline paths, `[source] paths = ["src", "tests"]` (Deviation D-1),
  `includes = ["vendor"]`. No local `version =`, no schema URL, no general settings and no general
  guard rules: the centre owns them
- [x] T008b Restate `[analyzer] excludes` as `["tests", "bench"]` in the local `mago.toml`.
  `mago.toml` array keys replace rather than merge, so the parent's `["test", "bench"]` does not
  cover this package's `tests/` directory (Deviation D-7)
- [x] T008a Create `mago.bat` at the repository root by copying the preset's `artifacts/mago.bat`
  verbatim. It is a tracked file, committed with no executable bit. Without it Infection cannot run
  Mago on Windows: it prefixes `PHP_BINARY` to the static analysis executable and PHP then reports
  a parse error inside the Rust binary. Harmless elsewhere, because `ExecutableFinder` skips
  non-executable files and the real `mago` on `PATH` wins. See webinertia/webware-tools issue 59
- [x] T009 Create empty `lint-baseline.toml` and `analysis-baseline.toml` by running
  `mago lint --generate-baseline --baseline lint-baseline.toml` and the analyzer equivalent
- [x] T010 Fix pass: run `mago format`, `mago lint`, `mago analyze`, `mago guard`; fix findings in
  `src/` and `tests/`. Guard findings are fixed in source, never suppressed or narrowed. The
  remediation is Deviation D-6: move `Cli\Command` to `Cli\Commands` and rename
  `interface Command` to `interface CommandInterface`
- [x] T010a After all four gates are clean, delete `phpstan.neon`, remove `phpstan/phpstan` from
  `require-dev` and confirm no PHPStan reference survives in `composer.json`

## Phase 4: Infection / Codecov / Renovate / PHPBench configs

- [x] T011 Create `infection.json5.dist` by copying the preset's `artifacts/infection.json5.dist`
  (`source.directories = ["src"]`, `staticAnalysisTool: "mago"`)
- [x] T012 Create `codecov.yml` by copying the preset's `artifacts/codecov.yml` verbatim
- [x] T013 Create `renovate.json` by copying the preset's `artifacts/renovate.json`
- [x] T014 Create `phpbench.json.dist` by copying the preset's `artifacts/phpbench.json.dist`; no
  `benchmarks/` directory required

## Phase 5: Required-workflow config, agent instructions, ignore rules

- [x] T015 Create `webware-ci.json` in the repository root from the preset's
  `artifacts/webware-ci.json`, replacing the `{{PLACEHOLDER}}` values with the Package Parameters:
  `php_versions` `["8.4", "8.5"]`, `run_integration` `false`, `integration_php_version` `""`,
  `enable_codecov` `true`, `enable_infection` `true`, `coverage_php_version` `"8.5"`, `min_msi`
  `"95"`, `min_covered_msi` `"95"`. Create no workflow file and add no `ci-target` property: the
  organization ruleset binds the required workflow to the repository
- [x] T016 Create `.github/copilot-instructions.md` from the preset's
  `artifacts/copilot-instructions.md`, replacing `{{PACKAGE_TITLE}}`
- [x] T017 Replace `.gitignore` with the preset's `artifacts/gitignore.txt` verbatim. It carries the
  tool caches and keeps `.specify/` and `specs/` tracked. It deliberately does not ignore
  `/mago.bat`: that file is tracked, because Infection needs it on Windows (see
  webinertia/webware-tools issue 59)
- [x] T017a Merge `.gitattributes` from the preset's `artifacts/gitattributes.txt` with the existing
  `bin/asciidoc-php-multi-repo text eol=lf` entry, and correct `/test/ export-ignore` to
  `/tests/ export-ignore` (Deviation D-3)

## Phase 6: README badges

- [x] T018 Add the badge block from the preset's `artifacts/readme-badges.md` with `ORG`
  `webinertia`, `REPO` `asciidoc-php-multi-repo`, `DEFAULT_BRANCH` `1.0.x`. Only the Required CI
  badge is advertised: the package is not published on Packagist, there is no `LICENSE` file, and
  no codecov or Stryker report exists for `1.0.x` yet (Deviation D-4). The badge points at
  `required/webinertia/.github/.github/workflows/continuous-integration.yml`, the workflow the
  organization config repository actually ships

## Phase 7: Containerized development environment

- [x] T019 Replace `Dockerfile` with a byte-identical copy of the preset's `artifacts/Dockerfile`
  (5400 bytes, SHA256 `0b6a454d24fb24e52781b4ae43a3bc2451a5645045c2535508db24c982f90799`): PHP CLI,
  Composer, Mago resolved from the webware-tools pin, `intl`/`pcntl`/`zip`/`pcov`, `WORKDIR /app`.
  `1.0.x` carried a transitional 3111-byte variant with the Mago install stage removed
  (Deviation D-5)
- [x] T020 Verify `compose.yml` provides the persistent `tooling` service and bind-mounts the
  repository at `/app`. Owned by the container session; not edited here
- [x] T021 Verify `.dockerignore` excludes `vendor/`, `.git/`, caches and logs. Owned by the
  container session; not edited here
- [x] T022 Create `.devcontainer/devcontainer.json` by copying the preset's
  `artifacts/devcontainer.json` (references `compose.yml` via `dockerComposeFile` plus
  `service: tooling`, `workspaceFolder /app`, PHP/Xdebug/EditorConfig extensions)
- [x] T023 Verify the development environment: `docker compose up -d`, then
  `docker compose exec tooling composer test` and
  `docker compose exec tooling mago format --check` succeed with no native PHP toolchain on the
  host. The tooling container is `command: sleep infinity`, so `docker compose down` follows every
  session that starts it

## Verification

- [x] T024 Run the full local check inside the container: `mago format --check`, `mago lint`,
  `mago analyze`, `mago guard`, `composer test`, `composer test-coverage`, `composer sa`,
  `composer check-all`. Report the test count and assertion count
- [x] T025 Verify the ignore/track boundary: `git check-ignore mago.bat` reports nothing and
  `git ls-files --error-unmatch mago.bat` succeeds, so the Windows shim is tracked;
  `git ls-files .specify` lists files, so the spec-kit scaffolding and installed preset stay
  tracked
- [x] T026 Push the branch and open a ready PR against `1.0.x`, stating that the repository is
  ready for the coordinator's `ci-target = true` flip. No merge, no auto-merge
- [x] T027 Confirm all committed files use LF line endings (no CRLF); `git ls-files --eol` must show
  no `crlf` entries
