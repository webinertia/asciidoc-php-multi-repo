# Tasks: Webware-Tools Alignment

**Input**: `specs/[###-feature]/spec.md`, `plan.md`

Replace package parameters (PHP versions, test namespaces) before executing. The MSI gates are 95
and are not a package parameter.

## Phase 1: composer.json

- [ ] T001 Align `composer.json` `require.php` to `~8.4.1 || ~8.5.0` (no `8.6.0-dev`), set
  `config.platform.php` to `8.4.99` in `composer.json`
- [ ] T002 Align `require-dev`: PHPUnit `^13.3.0`, add `infection/infection: ^0.35.0`,
  `phpbench/phpbench: ^1.7`, `roave/backward-compatibility-check: ^8.21.0`; keep package deps,
  `webware/webware-tools`, `roave/security-advisories`; remove legacy `phpstan/phpstan`,
  `phpstan/phpstan-phpunit`, `webware/coding-standard` in `composer.json`
- [ ] T003 Set `autoload-dev` namespaces: `WebwareTest\<Package>\` → `test/unit/`,
  `WebwareTestIntegration\<Package>\` → `test/integration/` in `composer.json`
- [ ] T004 Define scripts `test`, `test-coverage`, `test-integration`, `mutation-test`, and a
  `test-all` alias (`test` + `test-integration` + `mutation-test`); remove legacy
  `check-all`/`cs-check`/`cs-fix`/`sa` scripts in `composer.json`
- [ ] T005 Run `composer update` and commit `composer.lock`

## Phase 2: phpunit.xml.dist

- [ ] T006 Create `phpunit.xml.dist` by copying the preset's
  `artifacts/phpunit.xml.dist` (PHPUnit 13.1 schema, strict flags, suites
  `unit test` and `integration test`, `<source>` including `src`); remove legacy tooling:
  `.php-cs-fixer.dist.php`, `.php-cs-fixer.php`, `.php-cs-fixer.cache`, `phpstan.neon.dist`,
  `phpstan-baseline.neon`, `stubs/`, `.laminas-ci.json`
- [ ] T007 Add test scaffolding: at least one test in `test/unit/` and one in
  `test/integration/` so PHPUnit and Infection do not error on empty suites

## Phase 3: Mago tooling

- [ ] T008 Create `mago.toml` by copying the preset's `artifacts/mago.toml`
  (`extends = "vendor/webware/webware-tools/mago.toml"`, `php-version = "8.4.1"`, linter/analyzer
  baseline paths, source paths `["src", "test"]`); remove the legacy `mago.json`. The file stays the
  stub: no general settings and no general guard rules, since the centre owns them
- [ ] T008a Create `mago.bat` at the repository root by copying the preset's
  `artifacts/mago.bat` verbatim. It is a tracked file, not a local one, and it must be committed
  with no executable bit. Without it Infection cannot run Mago on Windows: it prefixes
  `PHP_BINARY` to the static analysis executable and PHP then reports a parse error inside the
  Rust binary. Harmless everywhere else, because `ExecutableFinder` skips non-executable files
  and the real `mago` on `PATH` wins. See webinertia/webware-tools issue 59
- [ ] T009 Create empty `lint-baseline.toml` and `analysis-baseline.toml`
- [ ] T010 Fix pass: run `mago format`, `mago lint`, `mago analyze`, `mago guard`; fix all
  findings in `src/`; baseline only maintainer-approved remainder. Guard findings are fixed in
  source - a boundary rule is never suppressed or narrowed to get green

## Phase 4: Infection / Codecov / Renovate / PHPBench configs

- [ ] T011 Create `infection.json5.dist` by copying the preset's `artifacts/infection.json5.dist`
- [ ] T012 Create `codecov.yml` by copying the preset's `artifacts/codecov.yml` verbatim
- [ ] T013 Create `renovate.json` by copying the preset's `artifacts/renovate.json`
- [ ] T014 Create `phpbench.json.dist` by copying the preset's `artifacts/phpbench.json.dist`;
  no `benchmarks/` directory required

## Phase 5: Required-workflow config + agent instructions

- [ ] T015 Create `webware-ci.json` in the repository root from the preset's
  `artifacts/webware-ci.json`, replacing the `{{PLACEHOLDER}}` values with the spec's Package
  Parameters (`php_versions`, `run_integration`, `integration_php_version`, `enable_codecov`,
  `enable_infection`, `coverage_php_version`, `min_msi`, `min_covered_msi`). Create no workflow
  file: the organization ruleset binds the required workflow to the repository.
- [ ] T016 Create `.github/copilot-instructions.md` from the preset's
  `artifacts/copilot-instructions.md`, replacing `{{PACKAGE_TITLE}}`
- [ ] T017 Create `.gitignore` by copying the preset's `artifacts/gitignore.txt`
  verbatim, replacing the existing file. It carries the tool caches and the
  `.specify/` and `/specs/` scaffolding. It deliberately does not ignore
  `/mago.bat`: that file is tracked, because Infection needs it on Windows (see
  webinertia/webware-tools issue 59)

## Phase 6: README badges

- [ ] T018 Add the standard badge block from the preset's `artifacts/readme-badges.md`,
  replacing `{{PACKAGE_NAME}}`, `{{ORG}}`, `{{REPO}}`, `{{DEFAULT_BRANCH}}` (PHP version,
  latest version, license, Required CI, codecov, Mutation testing). CI and codecov
  badge URLs carry no `?branch=` parameter, so they always point at the default branch. The
  Stryker mutation badge URL embeds the branch segment; update that segment in both the badge
  URL and the dashboard link whenever the default branch changes. Update `README.md`

## Phase 7: Containerized development environment

- [ ] T019 Create `Dockerfile` by copying the preset's `artifacts/Dockerfile` (PHP CLI +
  Composer + Mago + Xdebug, `TARGETARCH`-aware Mago asset selection,
  `intl`/`pcntl`/`zip`/`pcov` extensions, Xdebug installed but off by default, `WORKDIR /app`)
- [ ] T020 Create `compose.yml` by copying the preset's `artifacts/compose.yml` (a persistent,
  interactive `tooling` service: `sleep infinity` command, `stdin_open`/`tty`, named volumes for
  `vendor/` and the Composer cache, `host.docker.internal` host-gateway for Xdebug); for packages
  whose tests need MySQL, uncomment the opt-in `mysql` service (and the `depends_on` block on
  `tooling`) and point the package's `mysql.local.php` at host `mysql`, port `3306`; uncomment the
  `phpmyadmin` service (http://localhost:8080) for a web UI
- [ ] T021 Create `.dockerignore` by copying the preset's `artifacts/.dockerignore`
  (exclude `vendor/`, `.git/`, `.github/`, `.devcontainer/`, `.specify/`, `specs/`, caches,
  `*.log`)
- [ ] T022 Create `.devcontainer/devcontainer.json` by copying the preset's
  `artifacts/devcontainer.json` (references `compose.yml` via `dockerComposeFile` +
  `service: tooling`, `workspaceFolder /app`, PHP/Xdebug/EditorConfig extensions)
- [ ] T023 Verify the development environment: `docker compose up -d` then
  `docker compose exec tooling composer test` and `docker compose exec tooling mago format
  --check` succeed with no native PHP toolchain; `docker compose exec tooling php -m` lists
  `xdebug`; confirm "Reopen in Container" opens against the `tooling` service; for MySQL
  packages, confirm the `mysql` service reports healthy and the test suite connects

## Verification

- [ ] T024 Run full local check: `mago format --check && mago lint && mago analyze && mago
  guard`, `composer test`, `composer test-coverage`, `composer test-integration`,
  `composer mutation-test`, and `composer test-all`
- [ ] T025 Verify the ignore/track boundary: `git check-ignore mago.bat` reports nothing and
  `git ls-files --error-unmatch mago.bat` succeeds, so the Windows shim is tracked;
  `git ls-files .specify` and `git ls-files .github/skills` both list files, so the spec-kit
  scaffolding and installed presets are tracked rather than ignored
- [ ] T026 Push branch, open PR, confirm all CI jobs green (mago, test matrix, codecov,
  mutation-test)
- [ ] T027 Confirm all committed files use LF line endings (no CRLF); verify via
  `git ls-files --eol | grep -v 'w/lf'` returns nothing
