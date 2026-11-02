# Quickstart

This document takes a clean checkout to a rendered site, then shows how to watch it, preview it, and
onboard a new repository. Every command below is run from the directory that holds `site.json`.

## 1. Requirements

- PHP 8.4 or 8.5 with `curl`, `json`, and `mbstring`.
- Composer 2.
- `git`, because live discovery clones repositories.

Check the PHP version:

```sh
php -v
```

## 2. Install

For this package's own checkout:

```sh
composer install
```

For a site repository that consumes the package:

```sh
composer require --dev webware/asciidoc-php-multi-repo
```

Either way the command is `vendor/bin/asciidoc-php-multi-repo`. In this repository the wrapper at
`bin/asciidoc-php-multi-repo` does the same thing without the vendor path.

Confirm the command is reachable:

```sh
php bin/asciidoc-php-multi-repo --version
```

Expected output, where the version is whatever `composer.json` currently declares:

```
asciidoc-php-multi-repo 0.0.0-dev
```

Exit code 0.

## 3. Configure the site

A build needs two files in the directory you run the command from:

- `site.json` - site identity, discovery settings, and output paths.
- a manifest, only when you want to render without contacting GitHub.

Start from the pair in this repository. `site.json`:

```json
{
    "$schema": "./config/site.schema.json",
    "schemaVersion": 1,
    "site": {
        "name": "webware",
        "baseUrl": "https://docs.webinertia.net",
        "basePath": "/"
    },
    "organization": {
        "name": "webinertia",
        "propertyName": "doc-target",
        "onboardValue": "true",
        "tokenEnvVar": "DOC_DISCOVERY_TOKEN"
    },
    "source": {
        "directory": "docs"
    },
    "render": {
        "safeMode": "safe"
    },
    "projects": {
        "asciidoc-php-multi-repo": {
            "title": "asciidoc-php-multi-repo",
            "description": "Multi-repository documentation site builder for asciidoc-php.",
            "accent": "#7e5ae0"
        }
    },
    "output": {
        "directory": "build/docs",
        "cacheDirectory": "build/docs-cache",
        "reportPath": "build/docs-report.json"
    }
}
```

`manifest.json`, with one entry that points at the current directory:

```json
{
    "$schema": "./config/manifest.schema.json",
    "schemaVersion": 1,
    "repositories": [
        {
            "name": "asciidoc-php-multi-repo",
            "slug": "asciidoc-php-multi-repo",
            "defaultBranch": "1.0.x",
            "archived": false,
            "path": "."
        }
    ]
}
```

Two path rules matter:

- `output.directory`, `output.cacheDirectory`, and `output.reportPath` resolve against the working
  directory, so run the command from the directory that holds `site.json`.
- A manifest entry's `path` resolves against the manifest file's own directory, and an entry with a
  `path` is never cloned.

The configuration keys, their defaults, and their types are in `config/site.schema.json`. The author,
operator, and theme guides cover the parts an author or an operator changes.

## 4. First build

```sh
php bin/asciidoc-php-multi-repo build --config site.json --manifest manifest.json
```

Expected output on standard output:

```
asciidoc-php-multi-repo: 5 page(s)
output directory: /absolute/path/to/build/docs
```

The count is the number of pages written; this repository's own documentation tree produces five.
Exit code 0 means every project is `ok` or `skipped`.

Look at the result:

```sh
ls build/docs
```

```
asciidoc-php-multi-repo/
```

Inside that directory is the project's slug, then one directory per version, then one HTML file per
rendered page:

```sh
ls build/docs/asciidoc-php-multi-repo/v1
```

```
authoring.html
index.html
operating.html
theming.html
troubleshooting.html
```

To rebuild from scratch, ignoring the cache:

```sh
php bin/asciidoc-php-multi-repo build --config site.json --manifest manifest.json --full
```

To write the machine-readable report to a path of your choosing:

```sh
php bin/asciidoc-php-multi-repo build --config site.json --manifest manifest.json --report build/report.json
```

The report conforms to `contracts/report.schema.json` and is written for every outcome except a
configuration error raised before a configuration could be loaded.

## 5. Watch

`watch` rebuilds affected projects on change and serves the result:

```sh
php bin/asciidoc-php-multi-repo watch --config site.json --manifest manifest.json --port 8080
```

Then open <http://localhost:8080/>. Editing a source file rebuilds only the project that owns it. Add
`--verbose` for per-page detail on standard error, and `--timings` for per-phase timings.

## 6. Preview

`serve` serves an already built output directory and does not rebuild:

```sh
php bin/asciidoc-php-multi-repo serve --out build/docs --port 8080
```

Open <http://localhost:8080/>. A slug path resolves to its redirect, and a clean path resolves to
`index.html`, so redirects and clean URLs can be checked without a full rebuild.

Stop either command with Ctrl+C.

## 7. Onboard a new repository

Onboarding needs two things: the `doc-target` organization custom property set to the onboard value,
and a `docs/` tree on the repository's default branch.

1. As an organization owner, set the property on the repository. A repository administrator cannot do
   this, because the property is editable by organization actors only. New repositories arrive with
   `doc-target` set to `"false"` and are not published until an owner changes it.
2. Add the documentation tree. Versioned layout:

   ```
   docs/
     v1/
       index.adoc
       guide.adoc
   ```

   Unversioned layout:

   ```
   docs/
     index.adoc
     guide.adoc
   ```

   The entry page is `index.adoc`, or `README.adoc` when there is no `index.adoc`. Only `.adoc` and
   `.asciidoc` files are rendered.

3. Render. With live discovery, set the token named by `organization.tokenEnvVar` and run `build`
   without `--manifest`:

   ```sh
   export DOC_DISCOVERY_TOKEN=...
   php bin/asciidoc-php-multi-repo build --config site.json
   ```

   To render without contacting GitHub, add one manifest entry instead, with a `path` that points at
   a local checkout:

   ```json
   {
       "name": "my-project",
       "slug": "my-project",
       "defaultBranch": "main",
       "onboarded": true,
       "path": "../my-project"
   }
   ```

   An entry without `path` is cloned from the organization instead, which is what the CI workflows
   use.

4. Confirm the new pages:

   ```sh
   ls build/docs/my-project/v1
   ```

   The README onboarding section states every layout requirement, and
   `docs/v1/troubleshooting.adoc` lists each reason code with its cause and its fix.

Nothing in `site.json` changes when a repository is onboarded. A project appears with no per-project
override at all; overrides for the slug, title, description, accent, and display order are optional.

## 8. Exit codes

| Code | Meaning |
| --- | --- |
| 0 | Every project is `ok` or `skipped`. |
| 1 | At least one project is `partial` or `failed`; the rest of the site was published. |
| 2 | Invalid configuration, slug collision, or bad invocation; nothing was published. |
| 3 | Discovery could not authenticate, the property is missing, or the API was unreachable. |
| 4 | Unexpected failure. |

Code 1 is not a command failure: the site is incomplete, so surface the report and still publish the
output that was produced.

## 9. Where to go next

- [Author guide](docs/v1/authoring.adoc) - layout, entry pages, includes, links, and the outdated banner.
- [Operator guide](docs/v1/operating.adoc) - the property, the tokens, the secrets, and the triggers.
- [Theme guide](docs/v1/theming.adoc) - tokens, per-project accents, and light and dark behaviour.
- [Troubleshooting](docs/v1/troubleshooting.adoc) - every reason code, its cause, and its fix.
