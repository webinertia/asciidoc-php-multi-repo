# Webware Tools Alignment: Spec Kit Preset

Spec Kit preset for aligning a Webware package's CI/CD pipeline and dev tooling with the
organization's required CI workflow.

## What it provides

Four template overrides for Spec Kit:

| Template | Content |
|---|---|
| `spec-template` | Alignment spec skeleton: purpose, scope, Package Parameters table, functional requirements, success criteria |
| `plan-template` | Plan prefilled with the required-workflow contract (`webware-ci.json` keys, jobs, consumer obligations) and 6 implementation phases |
| `tasks-template` | Task list T001 - T027: composer, phpunit, mago, infection/codecov/renovate/phpbench, `webware-ci.json`, README badges, containerized dev environment |
| `constitution-template` | Webware constitution: CI alignment, PHPUnit 13 strict mode, Mago gates, PHP compatibility, naming |

## Reference artifacts (`artifacts/`)

The preset also ships ready-to-copy reference artifacts so alignment never depends on
inspecting another package:

| Artifact | Consumer destination |
|---|---|
| `phpunit.xml.dist` | `phpunit.xml.dist` |
| `mago.toml` | `mago.toml` |
| `infection.json5.dist` | `infection.json5.dist` |
| `codecov.yml` | `codecov.yml` |
| `renovate.json` | `renovate.json` |
| `phpbench.json.dist` | `phpbench.json.dist` |
| `webware-ci.json` | `webware-ci.json` in the repository root, beside `mago.toml` (replace `{{PLACEHOLDER}}`s with Package Parameters) |
| `copilot-instructions.md` | `.github/copilot-instructions.md` (replace `{{PACKAGE_TITLE}}`) |
| `gitattributes.txt` | merge into `.gitattributes` |
| `gitignore.txt` | `.gitignore` (copy verbatim) |
| `mago.bat` | `mago.bat` in the repository root (copy verbatim, commit non-executable - the Windows shim Infection needs to run Mago; see issue 59) |
| `readme-badges.md` | badge block in `README.md` (replace `{{PACKAGE_NAME}}`, `{{ORG}}`, `{{REPO}}`, `{{DEFAULT_BRANCH}}`) |
| `Dockerfile` | `Dockerfile` (PHP + Composer + Mago + Xdebug dev image) |
| `compose.yml` | `compose.yml` (persistent, interactive `tooling` service - the source of truth) |
| `.dockerignore` | `.dockerignore` (exclude `vendor/`, `.git/`, `.devcontainer/`, caches from build context) |
| `devcontainer.json` | `.devcontainer/devcontainer.json` (thin VS Code wrapper around `compose.yml`) |

Each task names the `artifacts/` file it copies.

`webware-ci.json` is read at run time by the required workflow, which is bound to every repository
by the organization ruleset - there is no per-package wrapper workflow and no workflow ref to bump.
GitHub invokes the required workflow directly, so it cannot receive `with:` inputs; this file is how
a package varies its pipeline.

It is a template: `{{PHP_VERSIONS}}` is replaced by a JSON array (e.g. `["8.4", "8.5"]`), the
boolean placeholders are unquoted, and the rest are quoted strings. `integration_php_version` may be
left empty, which runs the integration suite on every matrix leg. Packages whose tests need a
database add the `db_*` keys:

```json
"db_image": "mysql:9.7",
"db_port": "3306",
"db_env_json": {"MYSQL_ROOT_HOST": "%", "MYSQL_DATABASE": "webware", "MYSQL_USER": "webware", "MYSQL_PASSWORD": "webware", "MYSQL_ALLOW_EMPTY_PASSWORD": "true"},
"db_health_cmd": "mysqladmin ping -h127.0.0.1 --silent",
"test_env_json": {"TESTS_ADAPTER_MYSQL_HOSTNAME": "127.0.0.1"}
```

The Package Parameters table in the generated spec is empty by design. Fill in package-specific
values before planning, reading the package's `composer.json` and `mago.toml` for the values it
already carries.

## Install

Requirements: `specify` CLI (`spec-kit` ≥ 0.16.0) and an initialized Spec Kit project
(`specify init`).

Local development (unreleased):

```bash
specify preset add --dev /path/to/webware-tools/presets/webware-alignment
```

From an installed dependency (the preset ships inside the package):

```bash
specify preset add --dev vendor/webware/webware-tools/presets/webware-alignment
```

From a release archive (substitute the tag - webware-tools tags carry no `v` prefix):

```bash
specify preset add --from https://github.com/webinertia/webware-tools/archive/refs/tags/<tag>.zip
```

From the catalog (once submitted):

```bash
specify preset add webware-alignment
```

Verify resolution:

```bash
specify preset resolve spec-template
specify preset list
```

Remove after testing:

```bash
specify preset remove webware-alignment
```

## Usage workflow

1. `specify init --here --force --integration copilot` (or your agent) in the target package.
2. Install this preset (one of the commands above).
3. `/speckit-specify "Align CI/CD and tooling with webware-tools"` - template is prefilled;
   fill the Package Parameters table with the package's values.
4. `/speckit-plan` - produces the artifact-by-artifact plan with the package's inputs.
5. `/speckit-tasks` - produces T001 - T027.
6. `/speckit-implement` - creates `webware-ci.json`, `phpunit.xml.dist`, `mago.toml`
   (extends `vendor/webware/webware-tools/mago.toml`), baselines, `infection.json5.dist`,
   `codecov.yml`, `renovate.json`, `phpbench.json.dist`, `Dockerfile`, `compose.yml`,
   `.dockerignore`, `.devcontainer/devcontainer.json`, composer changes + lock,
   `.github/copilot-instructions.md`, and README badges - all copied from the preset's
   `artifacts/` directory.

## Containerized development environment

The preset ships a self-contained, persistent development environment (`Dockerfile` +
`compose.yml`) so no native PHP toolchain is needed on the host. Works identically on Windows,
WSL, Linux, and macOS. `compose.yml` is the single source of truth; the VS Code Dev Container
(`.devcontainer/devcontainer.json`) is a thin wrapper over it, so plain Compose users are never
orphaned.

With VS Code, open the folder and **"Reopen in Container"**. Without VS Code (or from any
terminal):

```bash
docker compose up -d                 # build (first run) and start the persistent container
docker compose exec tooling bash     # attach an interactive shell
docker compose exec tooling composer install
docker compose exec tooling composer test
docker compose exec tooling composer test-coverage
docker compose exec tooling composer test-integration
docker compose exec tooling composer mutation-test
docker compose exec tooling mago format --check
docker compose exec tooling mago lint
docker compose exec tooling mago analyze
docker compose exec tooling mago guard
docker compose down                  # stop it
```

`vendor/` is the host's own copy - the project is bind-mounted at `/app`, so `composer install`
inside the container writes dependencies straight into the developer's working tree on disk. The
Composer cache lives in a named volume (Linux-native I/O, fast on Windows) so downloads survive
container rebuilds; it is only a cache.
Xdebug is installed but off by default - enable step debugging with `XDEBUG_MODE=debug`.

The Dockerfile derives Mago's version from the central `mago.toml` `version =` pin at build
time, resolved through `composer.lock`, so there is no `MAGO_VERSION` build arg to keep in sync.
`PHP_VERSION` tracks the package's latest supported 8.4 patch release.

For packages whose tests need MySQL (e.g. anything using `php-db/phpdb-mysql`), `compose.yml`
ships an opt-in `mysql` service - uncomment the
`mysql` service and the `depends_on` block on `tooling`, then point the package's local DB config
(e.g. `config/autoload/mysql.local.php`) at host `mysql`, port `3306`, using the service's
`MYSQL_*` credentials. The service mirrors the CI `db_image` / `db_env_json` Package Parameters,
so the dev database matches CI. An optional `phpmyadmin` web UI (http://localhost:8080) is also
included for inspecting the database.

## When to use / when not

Use for: any `webinertia` package that needs its first alignment to the organization's required
workflow.

Do not use for: feature work inside an already-aligned package, or packages outside the webware
ecosystem.
