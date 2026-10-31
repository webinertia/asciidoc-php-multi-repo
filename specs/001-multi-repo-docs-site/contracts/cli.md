# CLI Contract

**Version**: 1

**Status**: External contract. Breaking changes require a major version bump of the package.

**Consumer**: the standalone container action in `webinertia/asciidoc-action`, CI workflows in the
site repository, and local container users.

## Invocation

```
asciidoc-php-multi-repo <command> [options]
```

The command never reads from standard input, never prompts, and does not require a terminal. It
behaves identically when standard input is closed and when output is redirected.

## Commands

| Command | Behaviour | Writes site output |
| --- | --- | --- |
| `discover` | Lists onboarded repositories with their resolved slugs | no |
| `fetch` | Fetches documentation sources into the cache | no |
| `build` | discover, fetch, render, assemble, write, report | yes |
| `watch` | Rebuilds affected projects on change and serves the result | yes |
| `serve` | Serves an already built output directory | no |
| `validate` | Reports per-repository buildability and corrective actions | no |
| `clean` | Removes the cache and, with `--output`, the output directory | removes |

## Global options

| Option | Argument | Default | Notes |
| --- | --- | --- | --- |
| `-c`, `--config` | path | `./site.json` | Relative paths inside the file resolve against its directory |
| `-o`, `--out` | directory | `output.directory` | Overrides the configured output directory |
| `--cache` | directory | `output.cacheDirectory` | Overrides the configured cache directory |
| `--report` | path | `output.reportPath` | JSON report destination |
| `--concurrency` | integer | `render.concurrency` | Parallel page renders |
| `--project` | name | all | Repeatable. Accepts a slug or a repository name |
| `--ref` | `name=revision` | none | Repeatable. Overrides the ref for one repository |
| `--manifest` | path | none | Reads discovery from a manifest instead of the organization |
| `--full` | flag | off | Ignores cached state and rebuilds everything |
| `--base-path` | path | `site.basePath` | Overrides the base path |
| `--port` | integer | `8080` | Used by `watch` and `serve` |
| `--force` | flag | off | Allows `clean` to remove a path outside the config directory |
| `-q`, `--quiet` | flag | off | Errors only |
| `-v`, `--verbose` | flag | off | Per-page detail on standard error |
| `-t`, `--timings` | flag | off | Per-phase timings on standard error |
| `--no-color` | flag | off | Disables ANSI colour. Colour is also disabled when not a TTY |
| `-h`, `--help` | flag | | Exits 0 |
| `--version` | flag | | Prints the package version, exits 0 |

Unknown options exit 2 with usage on standard error.

## Exit codes

| Code | Name | Meaning |
| --- | --- | --- |
| 0 | Success | Every project is `ok` or `skipped` |
| 1 | Degraded | At least one project is `partial` or `failed`; the rest of the site was published |
| 2 | ConfigError | Invalid configuration, slug collision, or bad invocation; nothing was published |
| 3 | DiscoveryError | Discovery could not authenticate, the property is missing, or the API is unreachable; nothing was published |
| 4 | InternalError | Unexpected failure, including a determinism guard failure |

Code 1 is not a failure of the command. It means the site is incomplete and the caller should surface
the report while still publishing the produced output.

## Output streams

- Standard error: progress lines, warnings, per-page detail under `--verbose`, timings under
  `--timings`, and every error.
- Standard output: the final summary only, one block with the counts by status and the output path.
- `--quiet` suppresses standard error except errors and keeps the standard output summary.

No secret value, and no token, may appear on either stream.

## Environment

| Variable | Required | Purpose |
| --- | --- | --- |
| The name in `organization.tokenEnvVar` | For live discovery only | Property enumeration. The token needs Organization permissions, Custom properties, Read, with the organization as resource owner and public repository access. See the credentials section of `plan.md` |
| `NO_COLOR` | no | When set, colour is disabled |

The token must not be the same credential used to trigger `repository_dispatch` on the site
repository. Publishing `gh-pages` uses the site repository's own `GITHUB_TOKEN`.

No other environment variable affects behaviour. The command must not read `HOME`, a shell profile, or
a global config file.

## Machine-readable report

Written to `--report` when supplied. The schema is `contracts/report.schema.json`. The report is
written for every outcome except a configuration error raised before a config could be loaded, and in
that case the message on standard error is the only output.

## Stability

- Adding a command, an option, an exit code, or a report field is a minor change.
- Removing or renaming a command, an option, an exit code, or a required report field, or changing the
  meaning of an exit code, is a major change.
- The search index and `site.json` schemas are versioned independently by their own `schemaVersion`
  fields.

## Examples

```
asciidoc-php-multi-repo build --config site.json --report build/report.json
asciidoc-php-multi-repo validate --manifest tests/fixtures/manifest.json
asciidoc-php-multi-repo build --project message-bus --project messagebus-event --full
asciidoc-php-multi-repo serve --out build/site --port 8080
```
