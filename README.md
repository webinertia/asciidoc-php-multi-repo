# webware/asciidoc-php-multi-repo

[![Continuous Integration](https://github.com/webinertia/asciidoc-php-multi-repo/actions/workflows/required/webinertia/.github/.github/workflows/continuous-integration.yml/badge.svg)](https://github.com/webinertia/asciidoc-php-multi-repo/actions/workflows/required/webinertia/.github/.github/workflows/continuous-integration.yml)

Multi-repository site builder for asciidoc-php: aggregates docs from many repos into one GitHub Pages site

## Documentation

- [quickstart.md](quickstart.md) - install, configure, build, watch, and preview from a clean checkout.
- [Author guide](docs/v1/authoring.adoc) - the `docs/vN` layout, entry pages, includes, links, and the outdated-version banner.
- [Action setup guide](docs/v1/action-setup.adoc) - the `doc-target` property, the two tokens, the site secrets, and the publish triggers.
- [Theme guide](docs/v1/theming.adoc) - the token set, per-project accents, and light and dark behaviour.
- [Troubleshooting and reason codes](docs/v1/troubleshooting.adoc) - every reason code, its cause, and its fix.
- [GitHub action reference](docs/v1/action.adoc) - the container action's inputs, image tags, Linux runner constraint, and the consumer workflows.

The five AsciiDoc guides are rendered by this package from `docs/v1`. To rebuild them locally:

```sh
composer install
bin/asciidoc-php-multi-repo build --config site.json --manifest manifest.json
```

The site is written to `build/docs`.

## Onboarding a repository

Onboarding a repository takes two things and nothing else: the `doc-target` organization custom
property set to the onboard value, and a `docs/` tree that satisfies the layout rules below. No
change to this package and no change to the site's `site.json` is required (FR-074). The pipeline
proves it: `test/integration/Onboarding/OnboardingTest.php` starts from a site configuration that
names no project, adds a manifest entry with the onboard value set, and publishes that project's
pages.

### The `doc-target` organization property

| Field | Value |
| --- | --- |
| `value_type` | `true_false` |
| `source_type` | `organization` |
| `required` | `true` |
| `default_value` | `"false"` |
| `require_explicit_values` | `true` |
| `values_editable_by` | `org_actors` |

The property is boolean-valued and organization-scoped. It is modelled on the existing CI targeting
property, `ci-target`, and differs from it in four fields: `required` is `true` rather than `false`,
`default_value` is `"false"` rather than absent, `require_explicit_values` is `true` rather than
`false`, and `values_editable_by` is `org_actors` rather than `org_and_repo_actors` (FR-075).

Two consequences follow from those settings:

- Onboarding needs an organization owner. A repository administrator cannot set the property,
  because only organization actors may edit its values.
- Every repository carries an explicit value. A new repository arrives with `doc-target` set to
  `"false"`, and it is not published until an organization owner sets it to the onboard value.

The builder never creates or modifies the property, and the discovery token holds Custom properties
Read only, so `values_editable_by` has no effect on the builder. The operator guide covers the token
and the site secrets.

### Required `docs/` layout

A published repository has a documentation directory, `docs` by default, on its default branch. The
directory holds either version subdirectories or an unversioned tree.

**Versioned layout (FR-010).** Subdirectories whose names match `^v[1-9][0-9]*$` are published
versions, and the directory name is the version label. Version labels sort numerically, so `v10`
outranks `v2`.

```
docs/
  v1/
    index.adoc
    guide.adoc
    reference/
      nested.adoc
  v2/
    index.adoc
```

That tree publishes `/slug/v1/index.html`, `/slug/v1/guide.html`,
`/slug/v1/reference/nested.html`, and `/slug/v2/index.html`.

**Unversioned layout (FR-011).** When no version subdirectory exists, the documentation directory
itself is a single version labelled `latest`.

```
docs/
  index.adoc
  guide.adoc
```

That tree publishes `/slug/latest/index.html` and `/slug/latest/guide.html`.

**Mixed layout (FR-012).** When version subdirectories and loose AsciiDoc files both exist, the
version subdirectories win. Loose files are ignored and the build records a warning naming each one.

```
docs/
  loose.adoc        <- ignored, with a warning
  v1/
    index.adoc
```

**Entry page (FR-013).** Each version resolves its entry page in order `index.adoc`, then
`README.adoc`. When neither exists the project fails with reason `missing-entry`, and the report
names both expected names.

**Renderable sources (FR-014).** Only `.adoc` and `.asciidoc` files are rendered. A version that
contains no renderable source fails with reason `no-asciidoc-sources`. Markdown is not converted in
version 1.

### Optional per-project overrides

The site configuration may override a project's slug, title, description, accent colour, and
display order (FR-076). Every override is optional: a project appears with no override at all, using
its repository name, its default accent, and its discovery order.

### Reason codes a maintainer may see

| Code | Meaning |
| --- | --- |
| `no-asciidoc-sources` | The version contains no `.adoc` or `.asciidoc` file. |
| `missing-entry` | The version has neither `index.adoc` nor `README.adoc` at its root. |
| `fetch-failed` | The repository could not be cloned or fetched. |
| `archived` | The repository is archived and was skipped. |
| `include-outside-root` | An include target is outside the permitted directory. |
| `toc-target-missing` | A section at a contents level has no usable anchor. |
| `invalid-encoding` | A source file is not valid UTF-8. |
| `render-failed` | Conversion failed, or an include target does not exist. |
| `config-error` | `site.json` or the manifest is invalid. |
| `discovery-failed` | The organization property listing could not be read. |

The [troubleshooting guide](docs/v1/troubleshooting.adoc) gives the cause and the fix for each code.

## Deferred follow-on work

Three pieces of work are deliberately outside this package's version 1 scope. Each is recorded here
so the boundary is explicit, and each has a home.

- **Markdown ingestion.** Version 1 renders AsciiDoc only, so a Markdown-only documentation tree fails
  with `no-asciidoc-sources`. The conversion stage is deferred, and it lands behind the
  `SourcePreparer` seam in this package: `SourcePreparer` is the single place where a source file is
  read and turned into AsciiDoc input, and `SourceFormat` already distinguishes `Asciidoc`,
  `Markdown`, and `Unsupported`. The change adds a converter in `src/Source/`; no other stage moves.
- **The standalone action.** A container action that wraps this package's `build` command is deferred
  to its own repository, `webinertia/asciidoc-action`. Nothing in that repository is implemented
  here; this package guarantees the documented command contract it calls.
- **The site repository's workflows.** The nightly schedule, manual dispatch, and
  `repository_dispatch` workflow YAML is deferred to the site repository,
  `webinertia/docs.webinertia.net`. This package guarantees the CLI those workflows call and ships no
  workflow YAML for them.

## Running the gates in the container

Docker is the supported runtime. `Dockerfile`, `compose.yml`, and `.dockerignore` are copied from
the canonical `presets/webware-alignment/artifacts/` in `webinertia/webware-tools` rather than
hand-written, so the local environment matches the organization preset. The `tooling` service bind
mounts the working tree at `/app` and keeps its Composer cache in a named volume, so `vendor/` is
generated by the container and never by a host PHP installation.

```sh
docker compose build tooling
docker compose up -d
docker compose exec tooling composer install
docker compose exec tooling composer test
docker compose exec tooling composer sa
docker compose exec tooling composer validate --strict
docker compose exec tooling composer check-all
docker compose down
```

`docker compose run --rm tooling <command>` is the one-shot equivalent of starting the service and
executing a single command in it:

```sh
docker compose run --rm tooling composer test
```

PHP 8.5 is the default base image, pinned to the `8.5.12` patch release by the `PHP_VERSION` build
arg. `composer.json` allows 8.4 and 8.5, and the arg selects the base image:

```sh
docker compose build --build-arg PHP_VERSION=8.4 tooling
docker compose run --rm tooling composer check-all
```

The CLI entry point runs through the same service:

```sh
docker compose exec tooling bin/asciidoc-php-multi-repo --version
```
