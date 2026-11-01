# Tasks: Webware-Tools Alignment

**Input**: `specs/002-webware-alignment/spec.md`, `plan.md`

Package parameters are already applied throughout: PHP versions `8.4` and `8.5`, test roots
`test/unit` and `test/integration`, namespaces `WebwareTest\AsciidocPhpMultiRepo\` (unit) and
`WebwareTestIntegration\AsciidocPhpMultiRepo\` (integration). The MSI gates are 95 and are not a
package parameter.

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
- [x] T003 Verify `autoload-dev` maps `WebwareTest\AsciidocPhpMultiRepo\` to `test/unit/` and
  `WebwareTestIntegration\AsciidocPhpMultiRepo\` to `test/integration/`, matching
  `templates/plan-template.md` Phase 1
- [x] T003a Rename the test layout to the preset convention: `tests/` to `test/`,
  `tests/Unit/` to `test/unit/`, `tests/Contract/` to `test/integration/`, and rewrite the
  namespaces in all 30 test files. The `Contract` suite is then mapped onto `unit test`, not
  `integration test`: both files read a committed artifact and compare it to code, with no composed
  pipeline and no external boundary, so they are unit-level consistency checks. This removes the
  former deviations D-1, D-2 and D-3 and the `WARN Failed to walk /app/test`
- [x] T003b Move `ContractDocumentationTest` and `SchemaContractTest` from `test/integration/` to
  `test/unit/`, change their namespace to `WebwareTest\AsciidocPhpMultiRepo`, and add
  `test/integration/.gitkeep` so the empty `integration test` suite still resolves. No filler test
  is added. See Deviation D-7 for the resulting `composer test-integration` exit code
- [x] T004 Define scripts `test` (all suites, unchanged; see Deviation D-5), `test-coverage`
  (clover plus text), `test-integration` (testsuite `integration test`), `mutation-test`
  (`infection`), and `test-all` as the array `["@test", "@test-integration", "@mutation-test"]`
  rather than the preset's `&&`-joined string (Deviation D-6).
  Remove the legacy `sa` and `check-all` scripts: `templates/plan-template.md` Phase 1 says "No
  PHPStan package, no PHPStan script; remove legacy `check-all`/`cs-check`/`cs-fix`/`sa` scripts",
  and the preset README invokes the four `mago` commands directly. No wrapper is shipped in their
  place
- [x] T005 Run `composer update` inside the container and commit `composer.lock`

## Phase 2: phpunit.xml.dist

- [x] T006 Update `phpunit.xml.dist`: relative schema pointer
  `vendor/phpunit/phpunit/phpunit.xsd`, `bootstrap="vendor/autoload.php"`, `colors="true"`,
  `cacheDirectory=".phpunit.cache"`, `requireCoverageMetadata="true"`, and strict flags
  `failOnNotice`, `failOnDeprecation`, `failOnWarning`, `failOnRisky` all `true`. Preserve both
  suites: `unit test` over `test/unit` and `integration test` over `test/integration`. Keep
  `<source>` over `src`
- [x] T007 Add `#[CoversClass]` (or `#[CoversMethod]`) to every test class so
  `requireCoverageMetadata` holds. The `unit test` suite holds all 308 tests; the `integration test`
  suite is empty and stays empty, because the package has no composed pipeline to exercise. The
  owner directed that one coupling test against `webware/asciidoc-php` be written, but the hard stop
  applies: the dependency is required in `composer.json` and referenced nowhere in `src`, `bin` or
  `test`, and `BuildCommand` is a `StubCommand`, so there is no seam to drive. No scaffolding test
  is invented to fill the suite and no seam is invented to justify one (Deviation D-7)

## Phase 3: Mago tooling

- [x] T008 Create `mago.toml` from the preset's `artifacts/mago.toml`:
  `extends = "vendor/webware/webware-tools/mago.toml"`, `php-version = "8.4.1"`, linter and
  analyzer baseline paths, `[source] paths = ["src", "test", "bin/asciidoc-php-multi-repo"]`
  (Deviation D-4), `includes = ["vendor"]`. No local `version =`, no schema URL, no general
  settings and no general guard rules: the centre owns them
- [x] T008b Keep no local `[analyzer] excludes`. The shared config already excludes
  `["test", "bench"]`, and after the T003a rename this package's test directory is `test/`, so the
  parent exclusion applies as written. The former local override (D-7) is deleted
- [x] T008c Name the CLI entry point explicitly in `[source] paths` rather than adding the `bin`
  directory. `bin/asciidoc-php-multi-repo` has no `.php` extension, so directory walking discovers
  nothing; the pre-migration `phpstan.neon` analysed that file, so omitting it would be a coverage
  loss against the previous state (Deviation D-4)
- [x] T008a Create `mago.bat` at the repository root by copying the preset's `artifacts/mago.bat`
  verbatim. It is a tracked file, committed with no executable bit. Without it Infection cannot run
  Mago on Windows: it prefixes `PHP_BINARY` to the static analysis executable and PHP then reports
  a parse error inside the Rust binary. Harmless elsewhere, because `ExecutableFinder` skips
  non-executable files and the real `mago` on `PATH` wins. See webinertia/webware-tools issue 59
- [x] T009 Generate `lint-baseline.toml` and `analysis-baseline.toml` with
  `mago lint --generate-baseline --baseline lint-baseline.toml` and the analyzer equivalent, after
  the fix pass, never by hand. They carry the onboarding remainder: 931 lint findings in 361
  entries and 115 analyzer findings in 81 entries, both `variant = "loose"`, broken down by rule and
  by area in the PR body as the burndown list. `--remove-outdated-baseline-entries` runs after
  every move or rename, because the entries are keyed by file path
- [x] T010 Fix pass: run `mago format`, then the safe `mago lint --fix` and `mago analyze --fix`,
  then `mago guard`. Guard findings are fixed in source, never suppressed or narrowed; the
  remediation is Deviation D-3 (move `Cli\Command` to `Cli\Commands` and rename
  `interface Command` to `interface CommandInterface`). Never run `--potentially-unsafe` or
  `--unsafe`. The safe fixer clears the redundant-cast, fully-qualified-global-class-like and
  redundant-import families, plus `prefer-early-continue` and `yoda-conditions` in the entry point;
  everything else is baselined in T009 rather than suppressed inline
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
  `bin/asciidoc-php-multi-repo text eol=lf` entry. The artifact's `/test/ export-ignore` is kept
  verbatim because the directory is now `test/`

## Phase 6: README badges

- [x] T018 Add the badge block from the preset's `artifacts/readme-badges.md` with `ORG`
  `webinertia`, `REPO` `asciidoc-php-multi-repo`, `DEFAULT_BRANCH` `1.0.x`. Only the Required CI
  badge is advertised: the package is not published on Packagist, there is no `LICENSE` file, and
  no codecov or Stryker report exists for `1.0.x` yet (Deviation D-1). The badge points at
  `required/webinertia/.github/.github/workflows/continuous-integration.yml`, the workflow the
  organization config repository actually ships

## Phase 7: Containerized development environment

- [x] T019 Replace `Dockerfile` with a byte-identical copy of the preset's `artifacts/Dockerfile`
  (5400 bytes, SHA256 `0b6a454d24fb24e52781b4ae43a3bc2451a5645045c2535508db24c982f90799`): PHP CLI,
  Composer, Mago resolved from the webware-tools pin, `intl`/`pcntl`/`zip`/`pcov`, `WORKDIR /app`.
  `1.0.x` carried a transitional 3111-byte variant with the Mago install stage removed
  (Deviation D-2)
- [x] T020 Verify `compose.yml` provides the persistent `tooling` service and bind-mounts the
  repository at `/app`. Owned by the container session; not edited here
- [x] T021 Verify `.dockerignore` excludes `vendor/`, `.git/`, caches and logs. Owned by the
  container session; not edited here
- [x] T022 Create `.devcontainer/devcontainer.json` by copying the preset's
  `artifacts/devcontainer.json` (references `compose.yml` via `dockerComposeFile` plus
  `service: tooling`, `workspaceFolder /app`, PHP/Xdebug/EditorConfig extensions)
- [x] T023 Verify the development environment with one-off containers, so no long-lived container is
  left behind: `docker compose run --rm tooling composer test` and
  `docker compose run --rm tooling mago format --check` succeed with no native PHP toolchain on the
  host. If `docker compose up -d` is used instead, the tooling container is `command: sleep
  infinity`, so `docker compose down` must follow in the same session

## Verification

- [x] T024 Run the full local check inside the container: `mago format --check`, `mago lint`,
  `mago analyze`, `mago guard`, `composer test`, `composer test-coverage`. Report the test count and
  assertion count, and the Mago and PHP versions the image installed. `composer test-integration` is
  run as well and is expected to exit 1 with `No tests executed!`, because the suite is empty
  (Deviation D-7); that result is reported as measured rather than worked around. The owner directed
  that a single coupling test be written, and the hard stop blocked it because Group 1 exposes no
  seam where a resolved document reaches `webware/asciidoc-php`. The required
  workflow does not run that leg while `run_integration` is `false`
- [x] T025 Verify the ignore/track boundary: `git check-ignore mago.bat` reports nothing and
  `git ls-files --error-unmatch mago.bat` succeeds, so the Windows shim is tracked;
  `git ls-files .specify` lists files, so the spec-kit scaffolding and installed preset stay
  tracked
- [x] T026 Push the branch and open a ready PR against `1.0.x`, stating that the repository is
  ready for the coordinator's `ci-target = true` flip. No merge, no auto-merge
- [x] T027 Confirm all committed files use LF line endings (no CRLF); `git ls-files --eol` must show
  no `crlf` entries
