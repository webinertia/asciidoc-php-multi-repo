# asciidoc-php-multi-repo Constitution

Multi-repository documentation site builder for the webware fleet. This package aggregates
AsciiDoc documentation from many repositories into one static site suitable for GitHub Pages.
It builds on `webware/asciidoc-php` and never modifies it.

## Core Principles

### I. The Converter Is a Fixed Dependency

`webware/asciidoc-php` is consumed as a released Composer dependency and is never modified,
forked, patched, or vendored in this repository. Every capability this package needs must be
reachable through the converter's existing public surface: the `Asciidoc::convert()` and
`Asciidoc::convertFile()` facade, the `converter` option, document attributes (`linkcss`,
`stylesheet`, `stylesdir`, `noembed`, `toc`, `toc-placement`, `header_footer`), and the public
node classes used for section and block traversal.

Rationale: the converter has its own release line, its own consumers, and its own test suite.
A change made here cannot be allowed to force a converter release or to break converter
consumers. If a requirement truly cannot be met without a converter change, it is raised as a
separate issue against the converter and this package ships a documented workaround until then.

### II. Configuration Over Hard-Coded Convention

No organization name, repository name, custom property name, slug, host name, accent colour, or
output path may appear in source code, in tests that are not fixtures, or in shipped assets. All
such values are supplied by the site configuration file owned by the site repository.
Webinertia's values live in `webinertia/docs.webinertia.net`, not here.

Rationale: the builder must be reusable by a third party and must be usable by the GitHub
Action that is published separately. A hard-coded organization turns a generic tool into an
internal script and blocks the Action from being published for third parties.

### III. Deterministic Output

Given identical inputs, two builds must produce byte-identical output trees. Therefore:
no build timestamps, no clock-derived document attributes, no random or hash-of-time
identifiers, no absolute local paths, no machine names, and no unstable ordering. Projects,
versions, pages, table-of-contents entries, search entries, and report entries are always
emitted in a documented, stable order. Files are written only when their bytes change.

Rationale: the site is published to a git branch. Non-deterministic output turns every build
into a whole-tree commit, destroys reviewability of the published diff, and makes it impossible
to prove that an incremental build matches a full build.

### IV. Portability Without POSIX Assumptions

The package must run in a Linux container (the supported runtime), on macOS, and best effort on
native Windows. No POSIX-only functions, no `chmod`-dependent behaviour, no forward-slash-only
path joining, no shell string interpolation of user input, no reliance on case-sensitive file
systems, no assumptions about executable bits. External processes are started with an argument
array through `proc_open`, never through a shell string.

Rationale: the developer works on Windows, CI runs on Linux, and the Action runs in a container.
A build that only works on one of those is not shippable.

### V. No New Runtime Binaries

The runtime dependency set is PHP, Composer, and `git`. No Node.js, no npm, no Pagefind, no
Lunr, no Sass, no Ruby, no Asciidoctor, no Python. The theme is hand-written CSS, the search
user interface is hand-written vanilla JavaScript, and the search index is produced by PHP.
Docker is the supported way to obtain that runtime, not an additional dependency of the package.

Rationale: every extra binary is another installation step in the Action image, another
supply-chain surface, and another reason a third party cannot adopt the tool. PHP plus git
already exists wherever this tool is useful.

### VI. Isolated Failure Domains

A defect in one onboarded repository must never prevent the rest of the site from publishing.
Discovery, fetch, and render are isolated per project; page failures are isolated per page.
Every failure is recorded with the project slug, the version, the path where applicable, and a
machine-readable reason code. Exit codes are meaningful and stable: a partial site exits
non-zero so CI surfaces the problem, while still publishing everything that succeeded.

Rationale: the site is an aggregate of a fleet. One repository with a malformed include must not
take the documentation site offline.

### VII. Test-First Against a Fixture Organization

Every functional requirement has an automated test. Tests use a checked-in fixture organization
of small repositories covering, at minimum: a versioned project, an unversioned project, a
project with a broken page, a Markdown-only project, an archived repository, and a project with
mixed layout. Network access is confined to one opt-in integration test; the default test suite
runs offline. Golden-file tests assert exact rendered output for at least one project.

Rationale: the input surface is other repositories, which cannot be relied on in CI. Fixtures
make the behaviour of discovery, layout, redirects, and failure reporting provable and fast.

### VIII. Stable External Contract

The command-line interface, exit codes, JSON build report schema, search index schema, and
published URL layout are external contracts. They are documented in `contracts/` and versioned
with `schemaVersion` fields. A breaking change to any of them requires a major version bump of
this package and a migration note. Additive changes require a `schemaVersion` increment for the
affected document.

Rationale: the GitHub Action, the site repository workflows, and third-party consumers call
this contract. Silent changes break publishing without any visible error at the call site.

## Additional Constraints

### Technology and Environment

- PHP 8.4 and 8.5 only. Do not depend on PHP 8.6 features or on the `true-async` extension.
  Concurrency is implemented with a bounded worker pool that works without any extension.
- Runtime dependencies: `webware/asciidoc-php`, plus `ext-json` and `ext-curl`. Development
  dependencies follow the converter's conventions (PHPUnit 13, PHPStan level 10).
- The package name is `webware/asciidoc-php-multi-repo`; the namespace root is
  `Webware\AsciidocPhpMultiRepo\`.
- Docker Compose in this repository is the supported runtime path and is independent of the
  converter repository's compose file.
- Static hosting constraints apply: no server-side redirects, no server-side includes, a
  `.nojekyll` marker, and an optional `CNAME` file. Every redirect must work as a plain file.

### Secrets and Safety

- The discovery token is read from an environment variable named by configuration. It is never
  written to disk, never logged, never echoed in a report, and never included in output.
- Render input is treated as untrusted: safe mode is on by default, includes are confined to the
  project's own checkout, and generated HTML is never assembled from unescaped source text.

## Development Workflow and Quality Gates

- Specification first: `spec.md`, then `plan.md`, then `tasks.md`. Implementation follows the
  task order; each task group is one pull request.
- Gates for every pull request: `composer test` (unit and integration suites), `composer sa`
  (PHPStan level 10), and the determinism gate (build the fixture organization twice, compare a
  recursive SHA-256 manifest, require zero differences).
- Network-dependent tests are opt-in and never run by default.
- Changes to consumer repositories (documentation migration, workflow wiring) ship as separate
  pull requests in those repositories, never inside a builder pull request.
- Every violation of a principle must be recorded in the plan's Complexity Tracking table with
  the simpler alternative that was rejected and why.

## Governance

This constitution supersedes local habit and convenience. A pull request that conflicts with a
principle must either change the pull request or amend the constitution. Amendments require a
pull request that states the principle, the reason, the migration impact, and a version bump of
this file. Compliance is reviewed at plan time (Constitution Check) and at review time.

**Version**: 1.0.0 | **Ratified**: 2026-10-31 | **Last Amended**: 2026-10-31
