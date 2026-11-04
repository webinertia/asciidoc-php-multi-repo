# Builder Amendment: Container Action as the Builder Dependency

**Status**: Active. Amends the builder dependency recorded in `spec.md`, `plan.md`, and `tasks.md`
for feature 003. All three files are merged and are not edited. This file records the decision that
supersedes the parts of them that describe a Composer install in the consumer workflow.

**Date**: 2026-11-04

## Superseded model

The merged artifacts described this builder dependency:

- The site repository carries `composer.json` requiring `webware/asciidoc-php-multi-repo` with a
  pinned constraint, and a committed `composer.lock`.
- The workflow installs the builder's declared runtime: the supported PHP version, the required PHP
  extensions, and `git`. It then runs `composer install --no-dev --no-interaction` from the lock
  file.
- The build step invokes the installed CLI with `--config`, `--output`, and `--report`, and the
  workflow reads the exit code, the report JSON, and the output tree.
- The build environment is reconstructed in each site repository from that manifest and lock file.

That model makes every site repository responsible for reproducing a PHP build environment, and it
ties the builder's version to a lock file in every consumer.

## Settled model

The builder dependency is the published container action in `webinertia/asciidoc-php-multi-repo`,
consumed with one `uses:` line. The action is a Docker action:

- `runs.image` is `docker://ghcr.io/webinertia/asciidoc-php-multi-repo:1`.
- `runs.args` is `render --config <inputs.config> --out <inputs.output>`.
- `inputs.config` defaults to `site.json` and `inputs.output` defaults to `build/docs`.

The image is built from `docker/Dockerfile` by `.github/workflows/publish-image.yml` and pushed to
GHCR when a release tag is published. The image carries PHP 8.4, the extensions the package
declares, and `git`. It deliberately carries no Node, and the publish workflow fails the build if
`node`, `npm`, or `npx` is present in it. The root `Dockerfile` is the development toolchain image
and is never published.

A consumer therefore installs nothing. There is no `composer.json`, no `composer.lock`, no
`setup-php` step, and no `composer install` step in the site repository. PHP is not present on the
runner and is not needed there.

The reason is the owner's goal for the action: a GitHub Action backed by asciidoc-php that any
webware repository can use. One image holds the build environment, so each site repository consumes
it rather than reconstructing it. A change to the builder's runtime is made once, in the image, and
reaches consumers through the image tag.

## Verified behavior and the one unproven link

Running the action's exact argument vector, `render --config site.json --out build/docs`, in an
image built from the same `docker/Dockerfile` produced 7 pages with exit 0. The output tree
contained `.nojekyll`, `404.html`, the project pages, hashed assets, and `search-index.json`, and
the build wrote `build/docs-report.json` with `status: ok`. That proves the verb mapping, the
configuration load, schema validation, manifest discovery, and output writing.

It does not prove the GHCR pull. The package is still private, and the owner is making it public.
Until it is public, a consumer run cannot pull the image without authentication.

Release `1.0.0-alpha.4` published the tags `1.0.0-alpha.4`, `1`, and `latest` at one digest,
`sha256:92fee2e5cbdb807d02e31046c3e0f72425fbd9f9cfd7e19e2a7f7fee695ca119`. The publish workflow
derives those tags with `type=semver,pattern={{version}}`, `type=match,pattern=^(\d+),group=1`, and
`type=raw,value=latest`.

## Pinning, and where it is weaker

The action ref is the pin. A consumer writes:

[source,yaml]
----
      - name: Build and deploy the site
        uses: webinertia/asciidoc-php-multi-repo@1.0.0-alpha.4
----

The image inside `action.yml` is the major alias `:1`. That alias moves within major 1 as releases
are published, so a consumer that pins the action to a moving ref, or that pins to a tag whose
`action.yml` names `:1`, receives new patch releases and new prereleases without changing its
workflow. This trades byte-exact reproducibility for automatic patch and prerelease delivery.

A consumer that needs an exact image must pin the action to a tag whose `action.yml` names a
version-specific image. No released tag currently names one: every released `action.yml` names the
major alias. Until such a tag exists, the guarantee is ref-level, not byte-level, and it is weaker
than the lock file FR-046 required. This amendment does not claim otherwise.

## Which requirements and tasks this supersedes

`spec.md` remains as merged. This amendment supersedes:

- **FR-046**. The builder is not pinned in a committed dependency manifest and lock file, and the
  workflow does not install from one. The action ref is the pin, as described above.
- **FR-047**. The workflow does not provide the builder's runtime. The image provides PHP, the
  extensions, and `git`. The runner provides only the container runtime.
- The in-scope bullet "the pinned builder dependency" in the scope section. The scaffolding item is
  now the action ref, not a dependency manifest.
- The out-of-scope bullet naming `webinertia/asciidoc-action` as the standalone reusable action for
  consumers. The action now lives in `webinertia/asciidoc-php-multi-repo`, and this specification
  is where it is recorded.

`plan.md` remains as merged. This amendment supersedes:

- The Technical Context entry "Builder dependency", which describes a `composer.json` constraint and
  a committed `composer.lock`.
- The Technical Context entry "Builder invocation", where the workflow calls the builder's `build`
  command with `--config`, `--output`, and `--report`. The action calls `render`, which the
  entrypoint maps to `build`, and passes only `--config` and `--out`.
- Job step 3, "Install runtime".
- Job step 4, "Build", including its `--report $RUNNER_TEMP/report.json` argument. The CLI always
  writes a report, to `output.reportPath` from the site configuration when no `--report` is given,
  so the report location is now a site configuration value rather than a workflow argument.
- The rationale "Why the tree is not built inside the checkout". The action's `output` input is
  relative to the checkout, and `$RUNNER_TEMP` is not the documented destination.

`tasks.md` remains as merged. This amendment supersedes:

- **T003**, adding `composer.json` with a pinned constraint.
- **T004**, committing `composer.lock`.
- **T011**, adding runtime installation and `composer install`.
- The Phase 1 checkpoint, which requires a clean clone to install the builder from the lock file.
- The dependency note that T001, T003, and T004 block T011. T003 and T004 no longer exist.
- **T013**'s acceptance for `--output` resolving to `$RUNNER_TEMP/site` and `--report` resolving to
  `$RUNNER_TEMP/report.json`. The output is workspace-relative and the report path is
  `output.reportPath`. T013's exit code capture remains in force.
- The out-of-scope bullet naming `webinertia/asciidoc-action`.

Tasks that do not depend on how the builder is installed remain in force, including the triggers,
concurrency, permissions, guards, wholesale publish, change detection, and the run summary.

## The render verb and the manifest

`render` is the CLI's `build`. `docker/entrypoint.sh` maps the verb and passes the remaining
arguments through unchanged, so `render --config site.json --out build/docs` runs
`build --config site.json --out build/docs`.

A workflow that wants an offline build sets `ASCIIDOC_MANIFEST` in the step environment. The
entrypoint reads it and inserts `--manifest <path>` ahead of the passed arguments. A build driven
from a manifest performs no discovery API call, so it needs no `DOC_DISCOVERY_TOKEN` and no token of
any kind:

[source,yaml]
----
      - name: Build and deploy the site
        uses: webinertia/asciidoc-php-multi-repo@1.0.0-alpha.4
        with:
          config: site.json
        env:
          ASCIIDOC_MANIFEST: manifest.json
----

## Consequences

- The site repository holds no dependency manifest, no lock file, and no PHP toolchain step.
- The builder version changes by changing the `uses:` ref, not by updating a lock file.
- Within major 1, the major alias delivers patch and prerelease changes without a workflow change.
  A consumer that requires an exact image must wait for a tag whose `action.yml` names a
  version-specific image.
- The consumer cannot choose a PHP version or a PHP extension set. Those are fixed by the image.
- Container actions run only on Linux runners, which the boundary amendment already records.
- The GHCR package must be public for an unauthenticated pull. Until the owner completes that
  change, the pull is the one part of the path that is not verified.
- The report path is `output.reportPath` from the site configuration, which is
  `build/docs-report.json` in the current site configuration, not `$RUNNER_TEMP/report.json`.
