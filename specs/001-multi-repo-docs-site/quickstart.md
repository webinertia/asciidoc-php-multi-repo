# Quickstart: Multi-Repo Documentation Site Builder

**Feature**: `specs/001-multi-repo-docs-site`

This document describes the intended developer experience once the plan is implemented. It is the
acceptance walkthrough for SC-010 and the container story.

## Prerequisites

- Docker with the Compose plugin, for the supported path.
- Or, for native development: PHP 8.4 or 8.5 with `ext-json`, `ext-curl`, `ext-mbstring`, Composer,
  and `git` on `PATH`.
- A site repository containing `site.json`.
- A fine-grained personal access token with permission to read organization custom property values,
  exported as the variable named by `organization.tokenEnvVar` (default `DOC_DISCOVERY_TOKEN`).

## 1. Write `site.json`

Place it at the root of the site repository. Start from the example in `plan.md` and change the
organization, property, base URL, and hostname. Validate it:

```
asciidoc-php-multi-repo validate --config site.json
```

Expected: one line per onboarded repository with `ok` or a reason code and a corrective action, and
exit 0 when every repository would build.

## 2. Build

```
asciidoc-php-multi-repo build --config site.json --report build/report.json
```

Expected output on standard output:

```
Build complete
  projects: 2 ok, 0 partial, 0 failed, 0 skipped
  pages: 24 total, 24 rendered
  output: /path/to/site/build/site
  report: /path/to/site/build/report.json
```

Exit codes: 0 for a complete site, 1 when the site was published but incomplete, 2 for a
configuration error, 3 when discovery could not run, 4 for an internal error.

Inspect the result:

```
build/site/index.html                  landing page
build/site/message-bus/index.html      redirect to /message-bus/v2/
build/site/message-bus/v2/index.html   entry page
build/site/message-bus/search-index.json
build/site/.nojekyll
build/site/CNAME
```

## 3. Watch

```
asciidoc-php-multi-repo watch --config site.json --port 8080
```

Open `http://localhost:8080/`. Edit a page in an onboarded repository's working copy and save; the
affected project rebuilds within five seconds and the browser reload shows the change. Editing a
theme asset rebuilds every project.

## 4. Preview a built tree

```
asciidoc-php-multi-repo serve --out build/site --port 8080
```

`http://localhost:8080/message-bus/` serves the redirect and lands on the latest version.
`http://localhost:8080/message-bus/v1/` serves the older version with the outdated banner.
An unknown path returns the generated `404.html` with status 404.

## 5. With Docker Compose

```
DOC_DISCOVERY_TOKEN=... docker compose run --rm render
docker compose up watch
docker compose up preview
```

`render` writes the site to the mounted output directory and exits with the build's exit code.
`watch` and `preview` publish port 8080. The stack belongs to this repository and does not touch the
converter repository's stack.

## 6. Onboard a new repository

1. Migrate the project's documentation to AsciiDoc under `docs/`, one directory per published
   version: `docs/v1/index.adoc`, `docs/v2/index.adoc`, and so on. A project with a single unversioned
   `docs/` directory publishes at `/slug/latest/`.
2. Set the organization custom property `doc-target` to `true` on the repository.
3. Do nothing else. No change to `site.json`, no entry in a list, no code change.

The next build discovers the repository, renders it, adds a landing-page card, a switcher entry, and a
search index, and redirects `/slug/` to its highest version.

A repository whose documentation tree contains only Markdown fails with `no-asciidoc-sources` for
version 1, is excluded from the site, and makes the build exit 1. Markdown ingestion is planned
follow-on work.

## 7. Troubleshooting

| Symptom | Cause | Action |
| --- | --- | --- |
| Exit 2 naming two repositories | Slug collision | Set a distinct `slug` in `projects` for one of them |
| Exit 3 naming the organization and property | Bad token or missing property | Check the token's organization read access and the property name |
| `missing-entry` | No `index.adoc` or `README.adoc` in a version directory | Add an entry page, or change `source.entryCandidates` |
| `no-asciidoc-sources` | Documentation is Markdown only | Migrate to AsciiDoc |
| `include-outside-root` | An `include::` target resolves outside the version directory | Move the file inside the version directory |
| `toc-target-missing` | A section has no id | Add an explicit `[[anchor]]` or an id attribute |
| `invalid-encoding` | A source file is not UTF-8 | Re-save the file as UTF-8 |
| `render-failed` | The converter rejected the source | Read the message; it names the file and the converter error |
| Output churns in git | A template gained a timestamp | The determinism guard names the offending file |
