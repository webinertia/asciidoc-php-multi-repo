# Boundary Amendment: Publishing Architecture

**Status**: Active. Amends the publishing boundary recorded in `spec.md` and `plan.md` for
feature 003. Both files are merged and are not edited. This file records the decision that
supersedes the parts of them that describe a GitHub App.

**Date**: 2026-11-03

## Superseded model

The merged specification and plan described this boundary:

- An operator creates a GitHub App named `AsciiDoc PHPublisher`, installs it on the site
  repository only, and records the installation as `repository_selection: selected`.
- The App carries Repository permissions, Contents, Read and write, and no organization
  permission.
- The private key is an organization secret whose repository access list is limited to
  repositories that carry `doc-target: true`.
- The App ID is an organization variable.
- A consumer repository mints an App installation token and sends a `repository_dispatch` to the
  site repository. The trigger credential is that installation token.
- A consumer notification workflow is an optional addition to each consumer repository.

That model requires an App, a private key, an installation, and an installation id, and it makes
the App the distribution mechanism.

## Settled model

- Distribution is a GitHub Docker container action published from
  `webinertia/asciidoc-php-multi-repo`. A site repository consumes it with a single `uses:` line.
  The image is public on GHCR, so the consumer pulls it and installs nothing. The action does not
  install the package or its dependencies at run time. Container actions run only on Linux
  runners, so the documented runner is `ubuntu-latest`.
- Onboarding a documentation site is one workflow file in the site repository plus the
  `doc-target` custom property on each source repository.
- The action runs entirely inside the site repository with that repository's own `GITHUB_TOKEN`.
- For organizations, one fine-grained personal access token stored as `DOC_DISCOVERY_TOKEN` in the
  site repository closes the one gap `GITHUB_TOKEN` cannot close.
- A personal account cannot use custom properties, because they are organization-only. It lists
  its sources explicitly in the site configuration. The manifest unifies both paths, so the build
  step does not care which produced the source list. A build driven from a manifest skips
  discovery entirely and needs no token at all.
- Push-triggered freshness survives. Each source repository carries one small workflow, triggered
  on push, that POSTs a `repository_dispatch` of type `docs-source-updated` to the site
  repository. That workflow holds one fine-grained personal access token as the repository secret
  `DOCS_DISPATCH_TOKEN`, scoped to the site repository only.
- The site workflow triggers on a push to the site repository, on a schedule, on
  `workflow_dispatch`, and on `repository_dispatch`. The scheduled run is the safety net, and
  push freshness depends on the trigger workflow existing in each source repository.
- The reverse direction, the site notifying consumer repositories, is removed. Nothing in the
  settled model requires it.
- No GitHub App is created, installed, or rotated. There is no private key, no installation id,
  and no organization variable.

## Why GITHUB_TOKEN cannot cross repositories

`GITHUB_TOKEN` is an installation token for the repository whose workflow is running, narrowed by
that workflow's `permissions` block. In the site repository it can build and deploy the site, and
it can read public sources, because public clones need no authentication. It cannot enumerate the
organization, and it cannot read the `doc-target` custom property on a sibling repository, because
the property values endpoint is organization-scoped and the token is repository-scoped. Reading
that property requires a credential with an organization permission, which is exactly what
`DOC_DISCOVERY_TOKEN` is.

## Exact permission set

| Credential | Location | Permissions |
| --- | --- | --- |
| `DOC_DISCOVERY_TOKEN` | Site repository secret | Organization Custom properties, Read. Repository Contents, Read on the source repositories. No write permission of any kind. |
| `DOCS_DISPATCH_TOKEN` | Each source repository secret | Repository Contents, Read and write on the site repository only. No organization permission and no access to any other repository. |
| `GITHUB_TOKEN` | Site repository workflow | `contents: write` from the workflow `permissions` block. Pushes `gh-pages`. |

`DOC_DISCOVERY_TOKEN` is required only for organization discovery. `DOCS_DISPATCH_TOKEN` is the
only credential a source repository holds. The two are entirely separate: a leak in a source
repository cannot enumerate the organization.

## What this means for the merged artifacts

- `spec.md` and `plan.md` remain as merged. Where they describe the App, this amendment
  supersedes them.
- `tasks.md` tasks that create the App and set the organization variable and secret describe the
  superseded model. They are not reticked or reworded here; this amendment records the
  replacement boundary.
- `tasks.md` T075 and `plan.md` name `docs/v1/operating.adoc`. The page is now
  `docs/v1/action-setup.adoc`, titled "Action setup guide", because the guide covers action setup
  rather than App operation. The merged files keep the old path; this amendment records the
  current one.
- `docs/v1/action-setup.adoc` documents the settled model.

## Consequences

- There is no App to create, install, or rotate, and no installation id to record.
- A source repository without the trigger workflow is still published, but only by the scheduled
  run.
- The discovery token's repository access list must be kept in step with `doc-target` by hand.
